<?php

namespace classes\helpers;

use classes\plugins\SafeMySQL;
use classes\system\Constants;

/*
* Класс для обработки сообщений пользователю
*/
class ClassMessages {
    private static bool $infrastructureReady = false;

    /**
    * Записать сообщение пользователю.
    */
    public static function set_message_user($user_id, $author_id, $message, $status = 'info'): int|false {
        return self::setMessageUser((int) $user_id, (int) $author_id, (string) $message, (string) $status);
    }

    public static function setMessageUser(int $userId, int $authorId, string $message, string $status = 'info'): int|false {
        $message = trim($message);
        if ($userId <= 0 || $authorId <= 0 || $message === '') {
            return false;
        }

        self::ensureInfrastructure();
        $status = self::normalizeStatus($status);
        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET user_id = ?i, author_id = ?i, message_text = ?s, status = ?s, chat_id = ?i',
                Constants::USERS_MESSAGE_TABLE,
                $userId,
                $authorId,
                $message,
                $status,
                $authorId
            );
            $messageId = (int) SafeMySQL::gi()->insertId();

            ClassNotifications::upsertSourceNotification($userId, 'message', $messageId, [
                'text' => $message,
                'status' => $status,
                'showtime' => 0,
                'url' => '/admin/messages',
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
        return SafeMySQL::gi()->getAll(
            'SELECT message_id, user_id, author_id, message_text, created_at, read_at, status
             FROM ?n
             WHERE user_id = ?i' . $whereUnread . '
             ORDER BY created_at DESC, message_id DESC',
            Constants::USERS_MESSAGE_TABLE,
            $userId
        );
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
                'SELECT message_id, user_id, author_id, message_text, status, read_at
                 FROM ?n
                 WHERE message_id = ?i AND user_id = ?i
                 LIMIT 1',
                Constants::USERS_MESSAGE_TABLE,
                $messageId,
                $userId
            );
        } else {
            $row = SafeMySQL::gi()->getRow(
                'SELECT message_id, user_id, author_id, message_text, status, read_at
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
}
