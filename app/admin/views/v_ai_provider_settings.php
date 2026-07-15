<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$t = static fn(string $key, string $fallback): string => (string) ($lang[$key] ?? $fallback);
$profile = is_array($ai_profile ?? null) ? $ai_profile : [];
$context = is_array($ai_provider_settings_context ?? null) ? $ai_provider_settings_context : [];
$modelOptions = is_array($ai_model_options ?? null) ? $ai_model_options : [];
$field = static fn(string $key, mixed $default = '') => array_key_exists($key, $profile) ? $profile[$key] : $default;
$provider = (string) ($context['provider'] ?? 'openrouter');
$profileId = (int) $field('ai_profile_id', 0);
$modelListId = 'ai-model-options-' . preg_replace('~[^a-z0-9_-]+~i', '-', $provider);
$settings = is_array($context['settings'] ?? null) ? $context['settings'] : [];
$apiKeyLabel = $provider === 'sbercloud'
    ? $t('sys.ai_authorization_key', 'Authorization key')
    : $t('sys.ai_api_key', 'API key');
$apiKeyHelpKey = trim((string) ($context['api_key_help_key'] ?? ''));
$apiKeyHelp = $apiKeyHelpKey !== '' ? $t($apiKeyHelpKey, '') : '';
?>
<div class="row g-3" data-ai-provider-panel="<?= $h($provider) ?>">
    <div class="col-12">
        <div class="alert alert-light border mb-0">
            <strong><?= $h($context['provider_label'] ?? $provider) ?></strong>
            <span class="text-muted ms-1"><?= $h($t('sys.ai_provider_settings_hint', 'Provider-specific settings are loaded dynamically.')) ?></span>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <label class="form-label"><?= $h($t('sys.ai_api_base_url', 'API base URL')) ?></label>
        <input name="api_base_url" class="form-control" value="<?= $h($context['api_base_url'] ?? 'https://openrouter.ai/api/v1') ?>" required>
    </div>
    <?php if ($provider === 'yandex_cloud'): ?>
        <div class="col-12 col-lg-6">
            <label class="form-label"><?= $h($t('sys.ai_yandex_folder_id', 'Folder ID')) ?></label>
            <input name="provider_settings[folder_id]" class="form-control font-monospace" value="<?= $h($settings['folder_id'] ?? '') ?>" placeholder="b1g...">
            <div class="form-text"><?= $h($t('sys.ai_yandex_folder_id_help', 'Used to build model URIs such as gpt://<folder_ID>/yandexgpt-5-lite.')) ?></div>
        </div>
    <?php elseif ($provider === 'sbercloud'): ?>
        <div class="col-12 col-lg-6">
            <label class="form-label"><?= $h($t('sys.ai_sber_oauth_url', 'OAuth URL')) ?></label>
            <input name="provider_settings[oauth_url]" class="form-control" value="<?= $h($settings['oauth_url'] ?? 'https://ngw.devices.sberbank.ru:9443/api/v2/oauth') ?>" required>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <label class="form-label"><?= $h($t('sys.ai_sber_scope', 'Scope')) ?></label>
            <?php $scope = (string) ($settings['scope'] ?? 'GIGACHAT_API_PERS'); ?>
            <select name="provider_settings[scope]" class="form-select">
                <?php foreach (['GIGACHAT_API_PERS', 'GIGACHAT_API_B2B', 'GIGACHAT_API_CORP'] as $scopeOption): ?>
                    <option value="<?= $h($scopeOption) ?>" <?= $scope === $scopeOption ? 'selected' : '' ?>><?= $h($scopeOption) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-6 col-lg-3 d-flex align-items-center">
            <div class="form-check form-switch mt-4">
                <input type="hidden" name="provider_settings[ssl_verify]" value="0">
                <input type="checkbox" class="form-check-input" id="ai-sber-ssl-verify" name="provider_settings[ssl_verify]" value="1" <?= !empty($settings['ssl_verify']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="ai-sber-ssl-verify"><?= $h($t('sys.ai_sber_ssl_verify', 'Verify TLS certificate')) ?></label>
            </div>
        </div>
    <?php elseif ($provider === 'vk_cloud'): ?>
        <div class="col-12">
            <div class="alert alert-info mb-0">
                <?= $h($t('sys.ai_vk_cloud_hint', 'Set the VK Cloud OpenAI-compatible endpoint issued for your project. If the endpoint does not expose /models, enter the model slug manually.')) ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="col-12 col-lg-6">
        <label class="form-label"><?= $h($t('sys.ai_model', 'Model')) ?></label>
        <div class="input-group">
            <input
                name="model"
                class="form-control font-monospace"
                list="<?= $h($modelListId) ?>"
                autocomplete="off"
                value="<?= $h($field('model', $context['model'] ?? '')) ?>"
                placeholder="<?= $h($t('sys.ai_model_search_placeholder', 'Start typing to search provider models')) ?>"
                data-ai-model-input
                data-ai-model-provider="<?= $h($provider) ?>"
                data-ai-model-profile-id="<?= $profileId ?>"
                data-ai-model-list="<?= $h($modelListId) ?>"
            >
            <button type="button" class="btn btn-outline-secondary" data-ai-model-refresh title="<?= $h($t('sys.ai_refresh_models', 'Refresh models')) ?>">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>
        <datalist id="<?= $h($modelListId) ?>">
            <?php foreach ($modelOptions as $option): ?>
                <?php $modelId = (string) ($option['id'] ?? ''); ?>
                <?php if ($modelId !== ''): ?>
                    <option value="<?= $h($modelId) ?>"><?= $h((string) ($option['name'] ?? $modelId)) ?></option>
                <?php endif; ?>
            <?php endforeach; ?>
        </datalist>
        <div class="form-text" data-ai-model-help>
            <?= $h($t('sys.ai_model_search_help', 'The list is searched through AJAX and can also accept a model slug manually.')) ?>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <label class="form-label"><?= $h($apiKeyLabel) ?></label>
        <input type="password" name="api_key" autocomplete="new-password" class="form-control" placeholder="<?= !empty($context['has_api_key']) ? $h($t('sys.ai_api_key_keep_placeholder', 'Leave empty to keep the saved key')) : $h($t('sys.ai_api_key_new_placeholder', 'Paste API key')) ?>">
        <div class="form-text">
            <?= $h($t('sys.ai_saved_key', 'Saved key')) ?>:
            <strong><?= trim((string) ($context['api_key_mask'] ?? '')) !== '' ? $h($context['api_key_mask']) : $h($t('sys.not_set', 'not set')) ?></strong>.
            <?= $h($t('sys.ai_full_key_hidden', 'The full key is never displayed.')) ?>
            <?php if ($apiKeyHelp !== ''): ?>
                <?= $h($apiKeyHelp) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-lg-6 d-flex align-items-center">
        <div class="form-check form-switch mt-4">
            <input type="checkbox" class="form-check-input" id="ai-profile-enabled" name="enabled" value="1" <?= !empty($field('enabled')) ? 'checked' : '' ?>>
            <label class="form-check-label" for="ai-profile-enabled"><?= $h($t('sys.enabled', 'Enabled')) ?></label>
        </div>
    </div>
</div>
