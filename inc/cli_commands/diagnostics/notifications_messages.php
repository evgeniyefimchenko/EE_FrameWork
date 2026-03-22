<?php

use classes\helpers\ClassMessages;
use classes\helpers\ClassNotifications;
use classes\plugins\SafeMySQL;
use classes\system\Constants;

$bootstrapOutput = $eeCliBootstrapOutput ?? '';

$options = is_array($eeCliOptions ?? null) ? $eeCliOptions : [];
$userId = isset($options['user']) ? (int) $options['user'] : 1;
$authorId = isset($options['author']) ? (int) $options['author'] : 0;
$jsonOutput = array_key_exists('json', $options);

if ($userId <= 0) {
    fwrite(STDERR, "Invalid user id.\n");
    exit(1);
}

if ($authorId <= 0 || $authorId === $userId) {
    $authorId = (int) SafeMySQL::gi()->getOne(
        'SELECT user_id
         FROM ?n
         WHERE user_id <> ?i
         ORDER BY CASE WHEN user_role = 8 THEN 0 ELSE 1 END, user_id ASC
         LIMIT 1',
        Constants::USERS_TABLE,
        $userId
    );
}

if ($authorId <= 0) {
    fwrite(STDERR, "No valid author id found.\n");
    exit(1);
}

$report = [
    'timestamp' => date('c'),
    'bootstrap_output' => $bootstrapOutput,
    'user_id' => $userId,
    'author_id' => $authorId,
];

$report['schema'] = [
    'notifications' => ClassNotifications::repairSchema(),
    'messages' => ClassMessages::repairSchema(),
    'options_column_type' => SafeMySQL::gi()->getOne(
        'SELECT DATA_TYPE
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ?s AND TABLE_NAME = ?s AND COLUMN_NAME = ?s
         LIMIT 1',
        ENV_DB_NAME,
        Constants::USERS_DATA_TABLE,
        'options'
    ),
];

$indexes = [
    'idx_notifications_user',
    'idx_notifications_user_showtime',
    'idx_notifications_source_lookup',
    'uq_notifications_user_source',
    'idx_users_message_user_read',
    'idx_users_message_user_created',
];
$report['schema']['indexes'] = [];
foreach ($indexes as $indexName) {
    $tableName = str_starts_with($indexName, 'idx_users_message') ? Constants::USERS_MESSAGE_TABLE : Constants::USERS_NOTIFICATIONS_TABLE;
    $report['schema']['indexes'][$indexName] = (bool) SafeMySQL::gi()->getOne(
        'SELECT 1
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = ?s AND TABLE_NAME = ?s AND INDEX_NAME = ?s
         LIMIT 1',
        ENV_DB_NAME,
        $tableName,
        $indexName
    );
}

$before = [
    'notifications_count' => (int) SafeMySQL::gi()->getOne(
        'SELECT COUNT(*) FROM ?n WHERE user_id = ?i',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId
    ),
    'messages_count' => (int) SafeMySQL::gi()->getOne(
        'SELECT COUNT(*) FROM ?n WHERE user_id = ?i',
        Constants::USERS_MESSAGE_TABLE,
        $userId
    ),
];

$diagSuffix = (string) round(microtime(true) * 1000);
$diagNotificationSourceId = (int) substr($diagSuffix, -9);
$tempMessageIds = [];
$tempNotificationIds = [];

try {
    $notificationId = ClassNotifications::upsertSourceNotification($userId, 'diagnostic', $diagNotificationSourceId, [
        'text' => 'Diagnostic notification ' . $diagSuffix,
        'status' => 'warning',
        'showtime' => 0,
        'url' => '/admin/messages',
    ]);
    $tempNotificationIds[] = (int) $notificationId;

    $notificationRow = SafeMySQL::gi()->getRow(
        'SELECT notification_id, source_type, source_id, text, status, showtime
         FROM ?n
         WHERE notification_id = ?i
         LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        (int) $notificationId
    );

    $newShowtime = (int) round(microtime(true) * 1000) + 60000;
    $showtimeUpdated = ClassNotifications::set_reading_time($userId, $newShowtime, (int) $notificationId);
    $notificationAfterShowtime = SafeMySQL::gi()->getRow(
        'SELECT notification_id, showtime FROM ?n WHERE notification_id = ?i LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        (int) $notificationId
    );

    $deletedStandalone = ClassNotifications::deleteNotificationsBySource($userId, 'diagnostic', $diagNotificationSourceId);
    $notificationGone = !(bool) SafeMySQL::gi()->getOne(
        'SELECT 1 FROM ?n WHERE notification_id = ?i LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        (int) $notificationId
    );

    $messageId = ClassMessages::setMessageUser($userId, $authorId, 'Diagnostic message ' . $diagSuffix, 'info');
    $tempMessageIds[] = (int) $messageId;
    $linkedNotification = SafeMySQL::gi()->getRow(
        'SELECT notification_id, source_type, source_id, text
         FROM ?n
         WHERE user_id = ?i AND source_type = ?s AND source_id = ?i
         LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId,
        'message',
        (int) $messageId
    );

    $markRead = ClassMessages::markMessageAsRead((int) $messageId, $userId);
    $messageReadAt = SafeMySQL::gi()->getOne(
        'SELECT read_at FROM ?n WHERE message_id = ?i LIMIT 1',
        Constants::USERS_MESSAGE_TABLE,
        (int) $messageId
    );
    $linkedNotificationAfterRead = SafeMySQL::gi()->getOne(
        'SELECT 1 FROM ?n WHERE user_id = ?i AND source_type = ?s AND source_id = ?i LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId,
        'message',
        (int) $messageId
    );

    $messageIdForDelete = ClassMessages::setMessageUser($userId, $authorId, 'Diagnostic delete message ' . $diagSuffix, 'danger');
    $tempMessageIds[] = (int) $messageIdForDelete;
    $linkedNotificationToDelete = SafeMySQL::gi()->getRow(
        'SELECT notification_id, source_id
         FROM ?n
         WHERE user_id = ?i AND source_type = ?s AND source_id = ?i
         LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId,
        'message',
        (int) $messageIdForDelete
    );
    if (!empty($linkedNotificationToDelete['notification_id'])) {
        $tempNotificationIds[] = (int) $linkedNotificationToDelete['notification_id'];
    }

    $deleteMessage = ClassMessages::deleteMessage((int) $messageIdForDelete, $userId);
    $messageDeleted = !(bool) SafeMySQL::gi()->getOne(
        'SELECT 1 FROM ?n WHERE message_id = ?i LIMIT 1',
        Constants::USERS_MESSAGE_TABLE,
        (int) $messageIdForDelete
    );
    $linkedNotificationDeleted = !(bool) SafeMySQL::gi()->getOne(
        'SELECT 1 FROM ?n WHERE user_id = ?i AND source_type = ?s AND source_id = ?i LIMIT 1',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId,
        'message',
        (int) $messageIdForDelete
    );

    $report['smoke'] = [
        'standalone_notification_created' => !empty($notificationRow['notification_id']),
        'standalone_notification_row' => $notificationRow ?: null,
        'standalone_notification_showtime_updated' => $showtimeUpdated && (int) ($notificationAfterShowtime['showtime'] ?? 0) === $newShowtime,
        'standalone_notification_deleted' => $deletedStandalone > 0 && $notificationGone,
        'message_created' => (int) $messageId > 0,
        'message_notification_created' => !empty($linkedNotification['notification_id']),
        'message_mark_read' => $markRead && !empty($messageReadAt),
        'message_notification_removed_on_read' => !$linkedNotificationAfterRead,
        'message_deleted' => $deleteMessage && $messageDeleted,
        'message_notification_removed_on_delete' => $linkedNotificationDeleted,
    ];
} finally {
    if ($tempNotificationIds !== []) {
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE notification_id IN (?a)',
            Constants::USERS_NOTIFICATIONS_TABLE,
            array_values(array_unique(array_filter(array_map('intval', $tempNotificationIds))))
        );
    }

    SafeMySQL::gi()->query(
        'DELETE FROM ?n WHERE user_id = ?i AND source_type = ?s AND source_id = ?i',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId,
        'diagnostic',
        $diagNotificationSourceId
    );

    if ($tempMessageIds !== []) {
        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE message_id IN (?a)',
            Constants::USERS_MESSAGE_TABLE,
            array_values(array_unique(array_filter(array_map('intval', $tempMessageIds))))
        );
    }
}

$after = [
    'notifications_count' => (int) SafeMySQL::gi()->getOne(
        'SELECT COUNT(*) FROM ?n WHERE user_id = ?i',
        Constants::USERS_NOTIFICATIONS_TABLE,
        $userId
    ),
    'messages_count' => (int) SafeMySQL::gi()->getOne(
        'SELECT COUNT(*) FROM ?n WHERE user_id = ?i',
        Constants::USERS_MESSAGE_TABLE,
        $userId
    ),
];

$report['counts'] = [
    'before' => $before,
    'after' => $after,
];

$summaryChecks = [
    'schema_options_mediumtext' => strtolower((string) ($report['schema']['options_column_type'] ?? '')) === 'mediumtext',
    'schema_indexes_ok' => !in_array(false, $report['schema']['indexes'], true),
    'smoke_ok' => !empty($report['smoke']) && !in_array(false, $report['smoke'], true),
    'cleanup_notifications_ok' => $before['notifications_count'] === $after['notifications_count'],
    'cleanup_messages_ok' => $before['messages_count'] === $after['messages_count'],
];

$report['summary'] = $summaryChecks;
$report['summary']['ok'] = !in_array(false, $summaryChecks, true);

if ($jsonOutput) {
    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($report['summary']['ok'] ? 0 : 1);
}

echo "===== Notifications & Messages Diagnostics =====" . PHP_EOL;
echo "Timestamp: {$report['timestamp']}" . PHP_EOL;
echo "User ID: {$userId}" . PHP_EOL;
echo "Author ID: {$authorId}" . PHP_EOL;
echo PHP_EOL;

echo "[Schema]" . PHP_EOL;
echo "Options column type: " . ($report['schema']['options_column_type'] ?? 'n/a') . PHP_EOL;
foreach (($report['schema']['indexes'] ?? []) as $indexName => $ok) {
    echo ' - ' . $indexName . ': ' . ($ok ? 'yes' : 'no') . PHP_EOL;
}
echo PHP_EOL;

echo "[Smoke]" . PHP_EOL;
foreach (($report['smoke'] ?? []) as $name => $ok) {
    if (is_bool($ok)) {
        echo ' - ' . $name . ': ' . ($ok ? 'pass' : 'fail') . PHP_EOL;
    }
}
echo PHP_EOL;

echo "[Cleanup]" . PHP_EOL;
echo 'Notifications before/after: ' . $before['notifications_count'] . '/' . $after['notifications_count'] . PHP_EOL;
echo 'Messages before/after: ' . $before['messages_count'] . '/' . $after['messages_count'] . PHP_EOL;
echo PHP_EOL;

echo "Overall OK: " . (!empty($report['summary']['ok']) ? 'yes' : 'no') . PHP_EOL;

exit(!empty($report['summary']['ok']) ? 0 : 1);
