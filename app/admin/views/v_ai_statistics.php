<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$t = static fn(string $key, string $fallback): string => (string) ($lang[$key] ?? $fallback);
$stats = is_array($ai_profile_stats ?? null) ? $ai_profile_stats : [];
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <div>
                <h1 class="mb-1"><?= $h($t('sys.ai_statistics', 'AI statistics')) ?></h1>
                <div class="text-muted"><?= $h($t('sys.ai_statistics_hint', 'Basic summary for AI settings. Request metrics will be added later.')) ?></div>
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-primary" href="/admin/ai_profiles"><?= $h($t('sys.ai_profiles', 'AI profiles')) ?></a>
                <a class="btn btn-primary" href="/admin/ai_statistics"><?= $h($t('sys.ai_statistics', 'AI statistics')) ?></a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-3">
                <div class="card shadow-sm border h-100"><div class="card-body">
                    <div class="text-muted small"><?= $h($t('sys.ai_profiles_total', 'Total profiles')) ?></div>
                    <div class="fs-3 fw-bold"><?= (int) ($stats['total'] ?? 0) ?></div>
                </div></div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card shadow-sm border h-100"><div class="card-body">
                    <div class="text-muted small"><?= $h($t('sys.enabled', 'Enabled')) ?></div>
                    <div class="fs-3 fw-bold text-success"><?= (int) ($stats['enabled'] ?? 0) ?></div>
                </div></div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card shadow-sm border h-100"><div class="card-body">
                    <div class="text-muted small"><?= $h($t('sys.disabled', 'Disabled')) ?></div>
                    <div class="fs-3 fw-bold text-secondary"><?= (int) ($stats['disabled'] ?? 0) ?></div>
                </div></div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card shadow-sm border h-100"><div class="card-body">
                    <div class="text-muted small"><?= $h($t('sys.ai_profiles_with_api_key', 'With API key')) ?></div>
                    <div class="fs-3 fw-bold"><?= (int) ($stats['with_api_key'] ?? 0) ?></div>
                </div></div>
            </div>
        </div>

        <div class="card shadow-sm border">
            <div class="card-header"><strong><?= $h($t('sys.ai_call_log', 'AI call log')) ?></strong></div>
            <div class="card-body text-muted">
                <?= $h($t('sys.ai_call_log_placeholder', 'Requests, tokens, cost and error statistics will be connected after test calls and AI Gateway runtime are implemented.')) ?>
            </div>
        </div>
    </div>
</main>
