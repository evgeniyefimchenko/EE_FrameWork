<?php

namespace classes\helpers;

use classes\plugins\SafeMySQL;
use classes\system\Constants;

/*
* Класс для обработки сообщений пользователю
*/
class ClassMessages {
    private static bool $infrastructureReady = false;
    private static bool $schemaReady = false;

    /**
    * Записать сообщение пользователю.
    */
    public static function set_message_user($user_id, $author_id, $message, $status = 'info', array $options = []): int|false {
        return self::setMessageUser((int) $user_id, (int) $author_id, (string) $message, (string) $status, $options);
    }

    public static function setMessageUser(int $userId, int $authorId, string $message, string $status = 'info', array $options = []): int|false {
        $message = trim($message);
        if ($userId <= 0 || $authorId <= 0 || $message === '') {
            return false;
        }

        self::ensureInfrastructure();
        $status = self::normalizeStatus($status);
        $messageTitle = trim((string) ($options['title'] ?? ''));
        $notificationUrl = trim((string) ($options['url'] ?? '/admin/messages'));
        $notificationShowtime = max(0, (int) ($options['showtime'] ?? 0));
        $notificationIcon = trim((string) ($options['icon'] ?? ''));
        $notificationColor = trim((string) ($options['color'] ?? ''));
        $sourceType = self::normalizeSourceType((string) ($options['source_type'] ?? 'system'));
        $sourceId = max(0, (int) ($options['source_id'] ?? 0));
        $notificationPayload = isset($options['payload']) && is_array($options['payload']) ? $options['payload'] : [];
        if ($messageTitle !== '' && !isset($notificationPayload['title'])) {
            $notificationPayload['title'] = $messageTitle;
        }

        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET user_id = ?i, author_id = ?i, message_text = ?s, title = ?s, status = ?s, chat_id = ?i, url = ?s, source_type = ?s, source_id = ?i, payload_json = ?s',
                Constants::USERS_MESSAGE_TABLE,
                $userId,
                $authorId,
                $message,
                $messageTitle,
                $status,
                $authorId,
                $notificationUrl,
                $sourceType,
                $sourceId,
                $notificationPayload !== [] ? json_encode($notificationPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null
            );
            $messageId = (int) SafeMySQL::gi()->insertId();

            ClassNotifications::upsertSourceNotification($userId, 'message', $messageId, [
                'text' => $message,
                'status' => $status,
                'showtime' => $notificationShowtime,
                'url' => $notificationUrl,
                'icon' => $notificationIcon,
                'color' => $notificationColor,
                'payload' => $notificationPayload,
            ]);

            SafeMySQL::gi()->query('COMMIT');
            return $messageId > 0 ? $messageId : false;
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    /**
    * Вернёт все сообщения пользователю.
    */
    public static function get_messages_user($user_id): array {
        return self::getMessagesUser((int) $user_id, false);
    }

    public static function getMessagesUser(int $userId, bool $unreadOnly = false): array {
        if ($userId <= 0) {
            return [];
        }

        self::ensureInfrastructure();
        $whereUnread = $unreadOnly ? ' AND read_at IS NULL' : '';
        $rows = SafeMySQL::gi()->getAll(
            'SELECT message_id, user_id, author_id, message_text, title, created_at, read_at, status, url, source_type, source_id, payload_json
             FROM ?n
             WHERE user_id = ?i' . $whereUnread . '
             ORDER BY created_at DESC, message_id DESC',
            Constants::USERS_MESSAGE_TABLE,
            $userId
        );

        return array_map(static fn(array $row): array => self::normalizeMessageRow($row), (array) $rows);
    }

    /**
    * Вернёт все непрочитанные сообщения пользователю.
    */
    public static function get_unread_messages_user($user_id): array {
        return self::getMessagesUser((int) $user_id, true);
    }

    /**
    * Количество непрочитанных сообщений.
    */
    public static function get_count_unread_messages($user_id): int {
        $userId = (int) $user_id;
        if ($userId <= 0) {
            return 0;
        }

        self::ensureInfrastructure();
        return (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(message_id) FROM ?n WHERE user_id = ?i AND read_at IS NULL',
            Constants::USERS_MESSAGE_TABLE,
            $userId
        );
    }

    /**
    * Количество сообщений.
    */
    public static function get_count_messages($user_id): int {
        $userId = (int) $user_id;
        if ($userId <= 0) {
            return 0;
        }

        self::ensureInfrastructure();
        return (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(message_id) FROM ?n WHERE user_id = ?i',
            Constants::USERS_MESSAGE_TABLE,
            $userId
        );
    }

    public static function markMessageAsRead(int $messageId, ?int $userId = null): bool {
        if ($messageId <= 0) {
            return false;
        }

        self::ensureInfrastructure();
        $messageRow = self::getMessageRow($messageId, $userId);
        if (!$messageRow) {
            return false;
        }

        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            if ($userId !== null && $userId > 0) {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET read_at = NOW() WHERE message_id = ?i AND user_id = ?i',
                    Constants::USERS_MESSAGE_TABLE,
                    $messageId,
                    $userId
                );
            } else {
                SafeMySQL::gi()->query(
                    'UPDATE ?n SET read_at = NOW() WHERE message_id = ?i',
                    Constants::USERS_MESSAGE_TABLE,
                    $messageId
                );
            }
            ClassNotifications::deleteNotificationsBySource((int) $messageRow['user_id'], 'message', $messageId);
            SafeMySQL::gi()->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    public static function markAllMessagesAsRead(int $userId): int {
        if ($userId <= 0) {
            return 0;
        }

        self::ensureInfrastructure();
        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET read_at = NOW() WHERE user_id = ?i AND read_at IS NULL',
                Constants::USERS_MESSAGE_TABLE,
                $userId
            );
            $affected = (int) SafeMySQL::gi()->affectedRows();
            ClassNotifications::deleteNotificationsBySource($userId, 'message');
            SafeMySQL::gi()->query('COMMIT');
            return $affected;
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    public static function deleteMessage(int $messageId, ?int $userId = null): bool {
        if ($messageId <= 0) {
            return false;
        }

        self::ensureInfrastructure();
        $messageRow = self::getMessageRow($messageId, $userId);
        if (!$messageRow) {
            return false;
        }

        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            if ($userId !== null && $userId > 0) {
                SafeMySQL::gi()->query(
                    'DELETE FROM ?n WHERE message_id = ?i AND user_id = ?i',
                    Constants::USERS_MESSAGE_TABLE,
                    $messageId,
                    $userId
                );
            } else {
                SafeMySQL::gi()->query(
                    'DELETE FROM ?n WHERE message_id = ?i',
                    Constants::USERS_MESSAGE_TABLE,
                    $messageId
                );
            }
            ClassNotifications::deleteNotificationsBySource((int) $messageRow['user_id'], 'message', $messageId);
            SafeMySQL::gi()->query('COMMIT');
            return true;
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    public static function deleteAllMessages(int $userId): int {
        if ($userId <= 0) {
            return 0;
        }

        self::ensureInfrastructure();
        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE user_id = ?i',
                Constants::USERS_MESSAGE_TABLE,
                $userId
            );
            $affected = (int) SafeMySQL::gi()->affectedRows();
            ClassNotifications::deleteNotificationsBySource($userId, 'message');
            SafeMySQL::gi()->query('COMMIT');
            return $affected;
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        }
    }

    private static function getMessageRow(int $messageId, ?int $userId = null): ?array {
        if ($messageId <= 0) {
            return null;
        }

        self::ensureInfrastructure();
        if ($userId !== null && $userId > 0) {
            $row = SafeMySQL::gi()->getRow(
                'SELECT message_id, user_id, author_id, message_text, title, status, read_at, url, source_type, source_id, payload_json
                 FROM ?n
                 WHERE message_id = ?i AND user_id = ?i
                 LIMIT 1',
                Constants::USERS_MESSAGE_TABLE,
                $messageId,
                $userId
            );
        } else {
            $row = SafeMySQL::gi()->getRow(
                'SELECT message_id, user_id, author_id, message_text, title, status, read_at, url, source_type, source_id, payload_json
                 FROM ?n
                 WHERE message_id = ?i
                 LIMIT 1',
                Constants::USERS_MESSAGE_TABLE,
                $messageId
            );
        }

        return $row ?: null;
    }

    private static function normalizeStatus(string $status): string {
        $status = strtolower(trim($status));
        return in_array($status, ['primary', 'info', 'success', 'warning', 'danger'], true) ? $status : 'info';
    }

    public static function repairSchema(): array {
        self::ensureInfrastructure();

        $changes = [];
        $columnDefinitions = [
            'title' => 'ALTER TABLE ?n ADD COLUMN title varchar(255) NOT NULL DEFAULT \'\' AFTER message_text',
            'url' => 'ALTER TABLE ?n ADD COLUMN url varchar(1024) NULL DEFAULT NULL AFTER status',
            'source_type' => 'ALTER TABLE ?n ADD COLUMN source_type varchar(32) NOT NULL DEFAULT \'system\' AFTER url',
            'source_id' => 'ALTER TABLE ?n ADD COLUMN source_id int(11) unsigned NULL DEFAULT NULL AFTER source_type',
            'payload_json' => 'ALTER TABLE ?n ADD COLUMN payload_json longtext NULL DEFAULT NULL AFTER source_id',
        ];

        foreach ($columnDefinitions as $columnName => $sql) {
            if (!self::hasColumn(Constants::USERS_MESSAGE_TABLE, $columnName)) {
                SafeMySQL::gi()->query($sql, Constants::USERS_MESSAGE_TABLE);
                $changes[] = 'Added column ' . $columnName;
            }
        }

        if (!self::hasIndex(Constants::USERS_MESSAGE_TABLE, 'idx_users_message_user_read')) {
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD KEY idx_users_message_user_read (user_id, read_at)',
                Constants::USERS_MESSAGE_TABLE
            );
            $changes[] = 'Added idx_users_message_user_read';
        }

        if (!self::hasIndex(Constants::USERS_MESSAGE_TABLE, 'idx_users_message_user_created')) {
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD KEY idx_users_message_user_created (user_id, created_at)',
                Constants::USERS_MESSAGE_TABLE
            );
            $changes[] = 'Added idx_users_message_user_created';
        }

        if (!self::hasIndex(Constants::USERS_MESSAGE_TABLE, 'idx_users_message_source')) {
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD KEY idx_users_message_source (user_id, source_type, source_id)',
                Constants::USERS_MESSAGE_TABLE
            );
            $changes[] = 'Added idx_users_message_source';
        }

        return [
            'status' => 'ok',
            'changes' => $changes,
        ];
    }

    private static function ensureInfrastructure(): void {
        if (self::$infrastructureReady) {
            return;
        }

        $exists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            Constants::USERS_MESSAGE_TABLE
        );
        if ($exists === 0) {
            throw new \RuntimeException('Messages infrastructure is not installed. Run install/upgrade first.');
        }

        self::$infrastructureReady = true;
        if (!self::$schemaReady) {
            self::repairSchema();
            self::$schemaReady = true;
        }
    }

    private static function hasIndex(string $tableName, string $indexName): bool {
        $row = SafeMySQL::gi()->getOne(
            'SELECT 1
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = ?s AND TABLE_NAME = ?s AND INDEX_NAME = ?s
             LIMIT 1',
            ENV_DB_NAME,
            $tableName,
            $indexName
        );

        return (bool) $row;
    }

    private static function hasColumn(string $tableName, string $columnName): bool {
        $row = SafeMySQL::gi()->getOne(
            'SELECT 1
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?s AND TABLE_NAME = ?s AND COLUMN_NAME = ?s
             LIMIT 1',
            ENV_DB_NAME,
            $tableName,
            $columnName
        );

        return (bool) $row;
    }

    private static function normalizeSourceType(string $sourceType): string {
        $sourceType = strtolower(trim($sourceType));
        if ($sourceType === '') {
            return 'system';
        }

        return preg_replace('/[^a-z0-9_\-]/', '_', $sourceType) ?: 'system';
    }

    private static function normalizeMessageRow(array $row): array {
        $payloadRaw = $row['payload_json'] ?? null;
        $payload = [];
        if (is_string($payloadRaw) && trim($payloadRaw) !== '') {
            $decoded = json_decode($payloadRaw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $title = trim((string) ($row['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($payload['title'] ?? ''));
        }
        if ($title === '') {
            $title = self::fallbackTitleBySourceType((string) ($row['source_type'] ?? 'system'));
        }

        $row['title'] = $title;
        $row['url'] = trim((string) ($row['url'] ?? ''));
        $row['source_type'] = self::normalizeSourceType((string) ($row['source_type'] ?? 'system'));
        $row['source_id'] = (int) ($row['source_id'] ?? 0);
        $row['payload'] = $payload;

        return $row;
    }

    private static function fallbackTitleBySourceType(string $sourceType): string {
        return match (self::normalizeSourceType($sourceType)) {
            'billing', 'invoice', 'payment' => 'Оплата и счета',
            'object', 'owner_object', 'placement' => 'Объекты и размещение',
            'review' => 'Отзывы и модерация',
            default => 'Системное сообщение',
        };
    }
}
