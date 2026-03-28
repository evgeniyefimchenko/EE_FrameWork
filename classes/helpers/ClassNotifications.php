<?php

namespace classes\helpers;

use classes\plugins\SafeMySQL;
use classes\system\Constants;

/*
 * Класс для работы с уведомлениями
 * подключается в необходимых моделях
 */
class ClassNotifications {

    /**
     * @var callable|null $logCallback
     * Если установлено, все уведомления будут отправлены сюда ВМЕСТО БД.
     * Это используется для AJAX/CRON логгеров.
     */
    public static $logCallback = null;

    private static bool $infrastructureReady = false;
    private static array $legacyMigrationLocks = [];
    private const ALLOWED_STATUSES = ['primary', 'info', 'success', 'warning', 'danger'];

    public static function get_notifications_user($user_id, int $limit = 50): array {
        return self::getNotificationsUser((int) $user_id, $limit);
    }

    /**
     * Вернёт все активные уведомления пользователя.
     */
    public static function getNotificationsUser(int $userId, int $limit = 50): array {
        if ($userId <= 0) {
            return [];
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        self::purgeLegacyNotifications($userId);

        $rows = SafeMySQL::gi()->getAll(
            'SELECT
                notification_id AS id,
                user_id,
                source_type,
                source_id,
                text,
                status,
                showtime,
                url,
                icon,
                color,
                payload_json,
                created_at,
                updated_at
             FROM ?n
             WHERE user_id = ?i
             ORDER BY notification_id DESC
             LIMIT ?i',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId,
            max(1, $limit)
        );

        return array_map(static fn(array $row): array => self::normalizeNotificationRow($row), $rows);
    }

    public static function purgeLegacyNotifications(int $userId = 0): int {
        self::ensureInfrastructure();

        if ($userId > 0) {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE user_id = ?i AND source_type = ?s',
                Constants::USERS_NOTIFICATIONS_TABLE,
                $userId,
                'legacy'
            );
        } else {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE source_type = ?s',
                Constants::USERS_NOTIFICATIONS_TABLE,
                'legacy'
            );
        }

        return (int) SafeMySQL::gi()->affectedRows();
    }

    /**
     * Добавит оповещение пользователю.
     */
    public static function addNotificationUser($user_id, $notification = []): int|false {
        $userId = (int) $user_id;
        if ($userId <= 0 || !is_array($notification)) {
            return false;
        }

        if (self::$logCallback !== null) {
            $status = self::normalizeStatus((string) ($notification['status'] ?? 'info'));
            $text = trim((string) ($notification['text'] ?? 'Пустое уведомление'));
            call_user_func(self::$logCallback, "УВЕДОМЛЕНИЕ ({$status}): {$text}");
            return 0;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);

        $payload = self::normalizeNotificationPayload($notification);
        if ($payload['text'] === '') {
            return false;
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n SET ?u',
            Constants::USERS_NOTIFICATIONS_TABLE,
            [
                'user_id' => $userId,
                'source_type' => (string) ($payload['source_type'] ?? 'system'),
                'source_id' => !empty($payload['source_id']) ? (int) $payload['source_id'] : null,
                'text' => $payload['text'],
                'status' => $payload['status'],
                'showtime' => $payload['showtime'],
                'url' => $payload['url'],
                'icon' => $payload['icon'],
                'color' => $payload['color'],
                'payload_json' => $payload['payload_json'],
            ]
        );

        return (int) SafeMySQL::gi()->insertId();
    }

    /**
     * Совместимость со старым snake_case API.
     */
    public static function add_notification_user($user_id, $notification = []): int|false {
        return self::addNotificationUser($user_id, $notification);
    }

    /**
     * Upsert уведомления по связанной сущности.
     */
    public static function upsertSourceNotification(int $userId, string $sourceType, int $sourceId, array $notification = []): int|false {
        if ($userId <= 0 || $sourceId <= 0) {
            return false;
        }

        if (self::$logCallback !== null) {
            return self::addNotificationUser($userId, $notification);
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        return self::upsertSourceNotificationInternal($userId, $sourceType, $sourceId, $notification);
    }

    /**
     * Удалит все найденные уведомления по переданному вхождению текста.
     */
    public static function kill_notification_by_text($user_id, $text_notification): int {
        $userId = (int) $user_id;
        $text = trim((string) $text_notification);
        if ($userId <= 0 || $text === '') {
            return 0;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE user_id = ?i AND text LIKE ?s',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId,
            '%' . $text . '%'
        );

        return (int) SafeMySQL::gi()->affectedRows();
    }

    /**
     * Удалит оповещение по его id.
     */
    public static function killNotificationById($user_id, $id): bool {
        $userId = (int) $user_id;
        $notificationId = (int) $id;
        if ($userId <= 0 || $notificationId <= 0) {
            return false;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE user_id = ?i AND notification_id = ?i',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId,
            $notificationId
        );

        return true;
    }

    /**
     * Удалит оповещения по статусу.
     */
    public static function kill_notification_by_status($user_id, $status): int {
        $userId = (int) $user_id;
        $status = self::normalizeStatus((string) $status);
        if ($userId <= 0) {
            return 0;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE user_id = ?i AND status = ?s',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId,
            $status
        );

        return (int) SafeMySQL::gi()->affectedRows();
    }

    /**
     * Установит время следующего показа уведомления.
     */
    public static function set_reading_time($user_id, $reading_time, $id): bool {
        $userId = (int) $user_id;
        $notificationId = (int) $id;
        if ($userId <= 0 || $notificationId <= 0) {
            return false;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        SafeMySQL::gi()->query(
            'UPDATE ?n SET showtime = ?i WHERE user_id = ?i AND notification_id = ?i',
            Constants::USERS_NOTIFICATIONS_TABLE,
            max(0, (int) $reading_time),
            $userId,
            $notificationId
        );

        return true;
    }

    /**
     * Удалит все уведомления пользователя.
     */
    public static function kill_em_all($user_id): int {
        $userId = (int) $user_id;
        if ($userId <= 0) {
            return 0;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE user_id = ?i',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId
        );

        return (int) SafeMySQL::gi()->affectedRows();
    }

    /**
     * Удаляет уведомления по source_type/source_id.
     */
    public static function deleteNotificationsBySource(int $userId, string $sourceType, int|array|null $sourceId = null): int {
        if ($userId <= 0 || trim($sourceType) === '') {
            return 0;
        }

        self::ensureInfrastructure();
        self::migrateLegacyNotificationsForUser($userId);

        if (is_array($sourceId)) {
            $sourceIds = array_values(array_unique(array_filter(array_map('intval', $sourceId), static fn(int $id): bool => $id > 0)));
            if ($sourceIds === []) {
                SafeMySQL::gi()->query(
                    'DELETE FROM ?n WHERE user_id = ?i AND source_type = ?s',
                    Constants::USERS_NOTIFICATIONS_TABLE,
                    $userId,
                    trim($sourceType)
                );
            } else {
                SafeMySQL::gi()->query(
                    'DELETE FROM ?n WHERE user_id = ?i AND source_type = ?s AND source_id IN (?a)',
                    Constants::USERS_NOTIFICATIONS_TABLE,
                    $userId,
                    trim($sourceType),
                    $sourceIds
                );
            }
            return (int) SafeMySQL::gi()->affectedRows();
        }

        if ($sourceId !== null && (int) $sourceId > 0) {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE user_id = ?i AND source_type = ?s AND source_id = ?i',
                Constants::USERS_NOTIFICATIONS_TABLE,
                $userId,
                trim($sourceType),
                (int) $sourceId
            );
        } else {
            SafeMySQL::gi()->query(
                'DELETE FROM ?n WHERE user_id = ?i AND source_type = ?s',
                Constants::USERS_NOTIFICATIONS_TABLE,
                $userId,
                trim($sourceType)
            );
        }

        return (int) SafeMySQL::gi()->affectedRows();
    }

    /**
     * Отображает и очищает уведомления пользователя.
     */
    public static function showNotifications(int $userId): string {
        $notifications = self::getNotificationsUser($userId);
        if ($notifications === []) {
            return '';
        }

        $output = '';
        foreach ($notifications as $notification) {
            $status = $notification['status'] ?? 'info';
            $text = $notification['text'] ?? 'No message';
            $output .= '<div class="ee_notification alert alert-' . htmlspecialchars((string) $status, ENT_QUOTES) . ' alert-dismissible fade show" role="alert" style="display: none;">';
            $output .= $text;
            $output .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
            $output .= '</div>';
        }

        self::kill_em_all($userId);
        return $output;
    }

    public static function repairSchema(): array {
        self::ensureInfrastructure();

        $changes = [];
        $optionsType = self::getColumnType(Constants::USERS_DATA_TABLE, 'options');
        if ($optionsType !== '' && $optionsType !== 'mediumtext') {
            SafeMySQL::gi()->query(
                "ALTER TABLE ?n MODIFY options MEDIUMTEXT NOT NULL COMMENT 'Настройки интерфейса пользователя'",
                Constants::USERS_DATA_TABLE
            );
            $changes[] = 'Modified ee_users_data.options to MEDIUMTEXT';
        }

        if (!self::hasIndex(Constants::USERS_NOTIFICATIONS_TABLE, 'idx_notifications_user')) {
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD KEY idx_notifications_user (user_id)',
                Constants::USERS_NOTIFICATIONS_TABLE
            );
            $changes[] = 'Added idx_notifications_user';
        }

        if (!self::hasIndex(Constants::USERS_NOTIFICATIONS_TABLE, 'idx_notifications_user_showtime')) {
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD KEY idx_notifications_user_showtime (user_id, showtime)',
                Constants::USERS_NOTIFICATIONS_TABLE
            );
            $changes[] = 'Added idx_notifications_user_showtime';
        }

        if (!self::hasIndex(Constants::USERS_NOTIFICATIONS_TABLE, 'idx_notifications_source_lookup')) {
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD KEY idx_notifications_source_lookup (source_type, source_id)',
                Constants::USERS_NOTIFICATIONS_TABLE
            );
            $changes[] = 'Added idx_notifications_source_lookup';
        }

        if (!self::hasIndex(Constants::USERS_NOTIFICATIONS_TABLE, 'uq_notifications_user_source')) {
            SafeMySQL::gi()->query(
                'DELETE t1
                 FROM ?n AS t1
                 INNER JOIN ?n AS t2
                    ON t1.user_id = t2.user_id
                   AND t1.source_type = t2.source_type
                   AND t1.source_id = t2.source_id
                   AND t1.notification_id < t2.notification_id
                 WHERE t1.source_id IS NOT NULL',
                Constants::USERS_NOTIFICATIONS_TABLE,
                Constants::USERS_NOTIFICATIONS_TABLE
            );
            SafeMySQL::gi()->query(
                'ALTER TABLE ?n ADD UNIQUE KEY uq_notifications_user_source (user_id, source_type, source_id)',
                Constants::USERS_NOTIFICATIONS_TABLE
            );
            $changes[] = 'Added uq_notifications_user_source';
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

        $notificationsExists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            Constants::USERS_NOTIFICATIONS_TABLE
        );
        $userDataExists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            Constants::USERS_DATA_TABLE
        );
        if ($notificationsExists === 0 || $userDataExists === 0) {
            throw new \RuntimeException('Notifications infrastructure is not installed. Run install/upgrade first.');
        }

        self::$infrastructureReady = true;
    }

    private static function migrateLegacyNotificationsForUser(int $userId): void {
        if (!empty(self::$legacyMigrationLocks[$userId])) {
            return;
        }

        $optionsRow = SafeMySQL::gi()->getRow(
            'SELECT data_id, options FROM ?n WHERE user_id = ?i LIMIT 1',
            Constants::USERS_DATA_TABLE,
            $userId
        );
        if (!$optionsRow) {
            return;
        }

        $options = json_decode((string) ($optionsRow['options'] ?? ''), true);
        if (!is_array($options) || !is_array($options['notifications'] ?? null) || $options['notifications'] === []) {
            return;
        }

        SafeMySQL::gi()->query('START TRANSACTION');
        try {
            self::$legacyMigrationLocks[$userId] = true;
            $lockedRow = SafeMySQL::gi()->getRow(
                'SELECT data_id, options FROM ?n WHERE user_id = ?i LIMIT 1 FOR UPDATE',
                Constants::USERS_DATA_TABLE,
                $userId
            );
            $lockedOptions = json_decode((string) ($lockedRow['options'] ?? ''), true);
            if (!is_array($lockedOptions) || !is_array($lockedOptions['notifications'] ?? null) || $lockedOptions['notifications'] === []) {
                SafeMySQL::gi()->query('COMMIT');
                return;
            }

            $messageRows = SafeMySQL::gi()->getAll(
                'SELECT message_id, message_text, status FROM ?n WHERE user_id = ?i AND read_at IS NULL ORDER BY message_id ASC',
                Constants::USERS_MESSAGE_TABLE,
                $userId
            );
            $messageBuckets = [];
            foreach ($messageRows as $messageRow) {
                $key = self::buildMessageMatchKey(
                    (string) ($messageRow['message_text'] ?? ''),
                    (string) ($messageRow['status'] ?? 'info')
                );
                $messageBuckets[$key][] = (int) ($messageRow['message_id'] ?? 0);
            }

            foreach ($lockedOptions['notifications'] as $legacyNotification) {
                if (!is_array($legacyNotification)) {
                    continue;
                }

                $payload = self::normalizeNotificationPayload($legacyNotification);
                if ($payload['text'] === '') {
                    continue;
                }

                $matchKey = self::buildMessageMatchKey($payload['text'], $payload['status']);
                $messageId = !empty($messageBuckets[$matchKey]) ? (int) array_shift($messageBuckets[$matchKey]) : 0;

                if ($messageId > 0) {
                    self::upsertSourceNotificationInternal($userId, 'message', $messageId, $payload);
                    continue;
                }

                SafeMySQL::gi()->query(
                    'INSERT INTO ?n SET ?u',
                    Constants::USERS_NOTIFICATIONS_TABLE,
                    [
                        'user_id' => $userId,
                        'source_type' => 'legacy',
                        'source_id' => null,
                        'text' => $payload['text'],
                        'status' => $payload['status'],
                        'showtime' => $payload['showtime'],
                        'url' => $payload['url'],
                        'icon' => $payload['icon'],
                        'color' => $payload['color'],
                        'payload_json' => $payload['payload_json'],
                    ]
                );
            }

            unset($lockedOptions['notifications']);
            $encodedOptions = json_encode($lockedOptions, JSON_UNESCAPED_UNICODE);
            if ($encodedOptions === false) {
                $encodedOptions = '{}';
            }
            SafeMySQL::gi()->query(
                'UPDATE ?n SET options = ?s WHERE data_id = ?i',
                Constants::USERS_DATA_TABLE,
                $encodedOptions,
                (int) ($lockedRow['data_id'] ?? 0)
            );

            SafeMySQL::gi()->query('COMMIT');
        } catch (\Throwable $e) {
            SafeMySQL::gi()->query('ROLLBACK');
            throw $e;
        } finally {
            unset(self::$legacyMigrationLocks[$userId]);
        }
    }

    private static function upsertSourceNotificationInternal(int $userId, string $sourceType, int $sourceId, array $notification = []): int|false {
        $payload = self::normalizeNotificationPayload(array_merge($notification, [
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ]));
        if ($payload['text'] === '') {
            return false;
        }

        SafeMySQL::gi()->query(
            'INSERT INTO ?n
                (user_id, source_type, source_id, text, status, showtime, url, icon, color, payload_json)
             VALUES (?i, ?s, ?i, ?s, ?s, ?i, ?s, ?s, ?s, ?s)
             ON DUPLICATE KEY UPDATE
                text = VALUES(text),
                status = VALUES(status),
                showtime = VALUES(showtime),
                url = VALUES(url),
                icon = VALUES(icon),
                color = VALUES(color),
                payload_json = VALUES(payload_json),
                updated_at = CURRENT_TIMESTAMP',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId,
            $payload['source_type'],
            $payload['source_id'],
            $payload['text'],
            $payload['status'],
            $payload['showtime'],
            $payload['url'],
            $payload['icon'],
            $payload['color'],
            $payload['payload_json']
        );

        $notificationId = (int) SafeMySQL::gi()->getOne(
            'SELECT notification_id FROM ?n WHERE user_id = ?i AND source_type = ?s AND source_id = ?i LIMIT 1',
            Constants::USERS_NOTIFICATIONS_TABLE,
            $userId,
            $payload['source_type'],
            $payload['source_id']
        );

        return $notificationId > 0 ? $notificationId : false;
    }

    private static function normalizeNotificationPayload(array $notification): array {
        $status = self::normalizeStatus((string) ($notification['status'] ?? 'info'));
        $sourceType = trim((string) ($notification['source_type'] ?? 'system'));
        if ($sourceType === '') {
            $sourceType = 'system';
        }
        $sourceId = !empty($notification['source_id']) ? (int) $notification['source_id'] : null;
        $text = trim((string) ($notification['text'] ?? ''));
        $showtime = max(0, (int) ($notification['showtime'] ?? 0));
        $meta = self::getStatusPresentation($status);
        $url = isset($notification['url']) && $notification['url'] !== ''
            ? (string) $notification['url']
            : ($sourceType === 'message' ? '/admin/messages' : '#');
        $icon = isset($notification['icon']) && trim((string) $notification['icon']) !== ''
            ? trim((string) $notification['icon'])
            : $meta['icon'];
        $color = isset($notification['color']) && trim((string) $notification['color']) !== ''
            ? trim((string) $notification['color'])
            : $meta['color'];

        $payloadJson = null;
        if (array_key_exists('payload_json', $notification) && $notification['payload_json'] !== null) {
            if (is_array($notification['payload_json']) || is_object($notification['payload_json'])) {
                $payloadJson = json_encode($notification['payload_json'], JSON_UNESCAPED_UNICODE) ?: null;
            } else {
                $payloadJson = (string) $notification['payload_json'];
            }
        }

        return [
            'text' => $text,
            'status' => $status,
            'showtime' => $showtime,
            'url' => $url,
            'icon' => $icon,
            'color' => $color,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'payload_json' => $payloadJson,
        ];
    }

    private static function normalizeNotificationRow(array $row): array {
        $status = self::normalizeStatus((string) ($row['status'] ?? 'info'));
        $meta = self::getStatusPresentation($status);
        return [
            'id' => (int) ($row['id'] ?? $row['notification_id'] ?? 0),
            'user_id' => (int) ($row['user_id'] ?? 0),
            'source_type' => (string) ($row['source_type'] ?? 'system'),
            'source_id' => !empty($row['source_id']) ? (int) $row['source_id'] : null,
            'text' => (string) ($row['text'] ?? ''),
            'status' => $status,
            'showtime' => max(0, (int) ($row['showtime'] ?? 0)),
            'url' => (string) ($row['url'] ?? '#'),
            'icon' => trim((string) ($row['icon'] ?? $meta['icon'])),
            'color' => trim((string) ($row['color'] ?? $meta['color'])),
            'payload_json' => $row['payload_json'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    private static function normalizeStatus(string $status): string {
        $status = strtolower(trim($status));
        return in_array($status, self::ALLOWED_STATUSES, true) ? $status : 'info';
    }

    private static function getStatusPresentation(string $status): array {
        return match ($status) {
            'primary' => ['icon' => 'fa-solid fa-envelope', 'color' => '#0d6efd'],
            'success' => ['icon' => 'fa-solid fa-check', 'color' => '#198754'],
            'warning' => ['icon' => 'fa-solid fa-triangle-exclamation', 'color' => '#ffc107'],
            'danger' => ['icon' => 'fa-solid fa-bolt', 'color' => '#dc3545'],
            default => ['icon' => 'fa-solid fa-circle-info', 'color' => '#61bdd1'],
        };
    }

    private static function buildMessageMatchKey(string $messageText, string $status): string {
        return sha1(trim($messageText) . '|' . self::normalizeStatus($status));
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

    private static function getColumnType(string $tableName, string $columnName): string {
        $type = SafeMySQL::gi()->getOne(
            'SELECT DATA_TYPE
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = ?s AND TABLE_NAME = ?s AND COLUMN_NAME = ?s
             LIMIT 1',
            ENV_DB_NAME,
            $tableName,
            $columnName
        );

        return strtolower((string) $type);
    }
}
