<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$snapshot = is_array($online_users_snapshot ?? null) ? $online_users_snapshot : [];
$minutes = (int) ($online_users_minutes ?? ($snapshot['minutes'] ?? 15));
$groups = is_array($snapshot['groups'] ?? null) ? $snapshot['groups'] : [];
$generatedAt = trim((string) ($snapshot['generated_at'] ?? ''));
$groupDefinitions = [
    'admins' => (string) ($lang['sys.dashboard_auth_online_admin_list'] ?? 'Администраторы'),
    'managers' => (string) ($lang['sys.dashboard_auth_online_manager_list'] ?? 'Менеджеры'),
    'users' => (string) ($lang['sys.dashboard_auth_online_user_list'] ?? 'Пользователи'),
    'others' => (string) ($lang['sys.dashboard_auth_online_other_list'] ?? 'Прочие роли'),
];
$hasRows = false;
foreach ($groups as $groupRows) {
    if (is_array($groupRows) && $groupRows !== []) {
        $hasRows = true;
        break;
    }
}
$formatDate = static function (string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '—';
    }
    return ee_format_utc_datetime($value, 'd.m.Y H:i');
};
?>
<div class="border rounded p-3 bg-light-subtle">
    <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
        <div class="small text-muted">
            <?= htmlspecialchars((string) ($lang['sys.dashboard_auth_online_loaded'] ?? 'Список онлайн-пользователей за последние')) ?>
            <strong><?= (int) $minutes ?></strong>
            <?= htmlspecialchars((string) ($lang['sys.dashboard_auth_online_minutes'] ?? 'минут')) ?>
        </div>
        <?php if ($generatedAt !== ''): ?>
            <div class="small text-muted">
                <?= htmlspecialchars((string) ($lang['sys.updated_at'] ?? 'Обновлено')) ?>:
                <strong><?= htmlspecialchars($formatDate($generatedAt), ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$hasRows): ?>
        <div class="small text-muted"><?= htmlspecialchars((string) ($lang['sys.dashboard_auth_online_empty'] ?? 'Сейчас никто не находится онлайн.')) ?></div>
    <?php else: ?>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($groupDefinitions as $groupKey => $groupLabel): ?>
                <?php $rows = is_array($groups[$groupKey] ?? null) ? $groups[$groupKey] : []; ?>
                <?php if ($rows === []): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <div>
                    <div class="fw-semibold mb-2">
                        <?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>
                        <span class="text-muted small">(<?= count($rows) ?>)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?= htmlspecialchars((string) ($lang['sys.user'] ?? 'Пользователь'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.email'] ?? 'Email'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.dashboard_auth_online_last_seen'] ?? 'Последняя активность'), ENT_QUOTES, 'UTF-8') ?></th>
                                    <th><?= htmlspecialchars((string) ($lang['sys.dashboard_auth_online_sessions'] ?? 'Сессий'), ENT_QUOTES, 'UTF-8') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="small text-muted">#<?= (int) ($row['user_id'] ?? 0) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($formatDate((string) ($row['last_seen_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) ($row['session_count'] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
