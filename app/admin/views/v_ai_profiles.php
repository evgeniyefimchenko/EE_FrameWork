<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$t = static fn(string $key, string $fallback): string => (string) ($lang[$key] ?? $fallback);
$profiles = is_array($ai_profiles ?? null) ? $ai_profiles : [];
$notice = is_array($ai_notice ?? null) ? $ai_notice : null;
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <div>
                <h1 class="mb-1"><?= $h($t('sys.ai_profiles', 'AI profiles')) ?></h1>
                <div class="text-muted"><?= $h($t('sys.ai_profiles_hint', 'Connection profiles for AI providers.')) ?></div>
            </div>
            <div class="btn-group">
                <a class="btn btn-primary" href="/admin/ai_profiles"><?= $h($t('sys.ai_profiles', 'AI profiles')) ?></a>
                <a class="btn btn-outline-primary" href="/admin/ai_statistics"><?= $h($t('sys.ai_statistics', 'AI statistics')) ?></a>
            </div>
        </div>

        <?php if ($notice): ?>
            <div class="alert alert-<?= $h($notice['type'] ?? 'info') ?>"><?= $h($notice['text'] ?? '') ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <strong><?= $h($t('sys.ai_profiles_list', 'Profiles list')) ?></strong>
                <a class="btn btn-sm btn-success" href="/admin/ai_profile/id/0">
                    <i class="fa-solid fa-plus"></i>&nbsp;<?= $h($t('sys.ai_profile_new', 'New AI profile')) ?>
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th><?= $h($t('sys.ai_profile_name', 'Name')) ?></th>
                                <th><?= $h($t('sys.ai_profile_code', 'Profile code')) ?></th>
                                <th><?= $h($t('sys.ai_provider', 'AI provider')) ?></th>
                                <th><?= $h($t('sys.status', 'Status')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($profiles as $profile): ?>
                            <tr>
                                <td><strong><?= $h($profile['name'] ?? '') ?></strong></td>
                                <td><span class="font-monospace"><?= $h($profile['profile_code'] ?? '') ?></span></td>
                                <td><?= $h($profile['provider'] ?? '') ?></td>
                                <td>
                                    <span class="badge bg-<?= !empty($profile['enabled']) ? 'success' : 'secondary' ?>">
                                        <?= !empty($profile['enabled']) ? $h($t('sys.enabled', 'Enabled')) : $h($t('sys.disabled', 'Disabled')) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/ai_profile/id/<?= (int) ($profile['ai_profile_id'] ?? 0) ?>">
                                        <?= $h($t('sys.open', 'Open')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($profiles === []): ?>
                            <tr>
                                <td colspan="5" class="text-muted"><?= $h($t('sys.ai_profiles_empty', 'No AI profiles have been created yet.')) ?></td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>
