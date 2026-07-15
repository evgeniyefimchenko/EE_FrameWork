<?php if (!defined('ENV_SITE')) exit(header('Location: /', true, 301)); ?>
<?php
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$t = static fn(string $key, string $fallback): string => (string) ($lang[$key] ?? $fallback);
$profile = is_array($ai_profile ?? null) ? $ai_profile : [];
$notice = is_array($ai_notice ?? null) ? $ai_notice : null;
$providerOptions = is_array($ai_provider_options ?? null) ? $ai_provider_options : [];
$isNew = !empty($ai_profile_is_new);
$field = static fn(string $key, mixed $default = '') => array_key_exists($key, $profile) ? $profile[$key] : $default;
$profileId = (int) $field('ai_profile_id', 0);
$providerSettingsUrl = '/admin/ajax_ai_provider_settings?profile_id=' . $profileId;
$modelsUrl = '/admin/ajax_ai_models?profile_id=' . $profileId;
$testUrl = !$isNew ? \classes\system\CsrfService::appendToUrl('/admin/ajax_ai_profile_test/id/' . $profileId) : '';
?>
<main>
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between mt-4 mb-3 flex-wrap gap-2">
            <div>
                <h1 class="mb-1"><?= $h($isNew ? $t('sys.ai_profile_new', 'New AI profile') : $t('sys.ai_profile', 'AI profile')) ?></h1>
                <div class="text-muted"><?= $h($t('sys.ai_profile_hint', 'AI provider profile card.')) ?></div>
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-primary" href="/admin/ai_profiles"><?= $h($t('sys.ai_profiles', 'AI profiles')) ?></a>
                <a class="btn btn-outline-primary" href="/admin/ai_statistics"><?= $h($t('sys.ai_statistics', 'AI statistics')) ?></a>
            </div>
        </div>

        <?php if ($notice): ?>
            <div class="alert alert-<?= $h($notice['type'] ?? 'info') ?>"><?= $h($notice['text'] ?? '') ?></div>
        <?php endif; ?>

        <div class="card shadow-sm border">
            <div class="card-header"><strong><?= $h($t('sys.ai_basic_settings', 'Basic settings')) ?></strong></div>
            <div class="card-body">
                <form method="post" action="<?= $h(\classes\system\CsrfService::appendToUrl('/admin/ai_profile/id/' . $profileId)) ?>" class="row g-3" data-ai-profile-form data-ai-provider-settings-url="<?= $h($providerSettingsUrl) ?>" data-ai-models-url="<?= $h($modelsUrl) ?>">
                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= $h($t('sys.ai_profile_name', 'Name')) ?></label>
                        <input name="name" class="form-control" value="<?= $h($field('name')) ?>" required>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= $h($t('sys.ai_profile_code', 'Profile code')) ?></label>
                        <input name="profile_code" class="form-control font-monospace" value="<?= $h($field('profile_code')) ?>" required pattern="[A-Za-z0-9_-]+">
                        <div class="form-text"><?= $h($t('sys.ai_profile_code_help', 'Use Latin letters, numbers, hyphen or underscore.')) ?></div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <label class="form-label"><?= $h($t('sys.ai_provider', 'AI provider')) ?></label>
                        <select name="provider" class="form-select" data-ai-provider-select>
                            <?php foreach ($providerOptions as $option): ?>
                                <?php $value = (string) ($option['value'] ?? ''); ?>
                                <?php if ($value !== ''): ?>
                                    <option value="<?= $h($value) ?>" <?= (string) $field('provider', 'openrouter') === $value ? 'selected' : '' ?>><?= $h($option['label'] ?? $value) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12" data-ai-provider-settings>
                        <?php include __DIR__ . '/v_ai_provider_settings.php'; ?>
                    </div>

                    <?php if (!$isNew): ?>
                        <div class="col-12">
                            <div class="border rounded p-3 bg-light">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <strong><?= $h($t('sys.ai_connection_test', 'Connection test')) ?></strong>
                                        <div class="text-muted small"><?= $h($t('sys.ai_connection_test_hint', 'Available after the profile is saved. The test checks provider access without running a completion.')) ?></div>
                                    </div>
                                    <button type="button" class="btn btn-outline-success" data-ai-test-connection data-ai-test-url="<?= $h($testUrl) ?>">
                                        <i class="fa-solid fa-plug-circle-check"></i>&nbsp;<?= $h($t('sys.ai_test_connection', 'Test connection')) ?>
                                    </button>
                                </div>
                                <div class="mt-3 d-none" data-ai-test-result></div>
                                <?php if (!empty($profile['last_test_at'])): ?>
                                    <div class="text-muted small mt-2">
                                        <?= $h($t('sys.ai_last_test', 'Last test')) ?>:
                                        <?= $h($profile['last_test_at']) ?>,
                                        <?= !empty($profile['last_test_ok']) ? $h($t('sys.success', 'Success')) : $h($t('sys.imports_error', 'Error')) ?>.
                                        <?= $h($profile['last_test_message'] ?? '') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info mb-0"><?= $h($t('sys.ai_test_after_save', 'Connection test will be available after saving the profile.')) ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-floppy-disk"></i>&nbsp;<?= $h($t('sys.ai_save_profile', 'Save profile')) ?>
                        </button>
                        <a class="btn btn-outline-secondary" href="/admin/ai_profiles"><?= $h($t('sys.ai_back_to_profiles', 'Back to list')) ?></a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
(function () {
    const form = document.querySelector('[data-ai-profile-form]');
    if (!form) {
        return;
    }

    const i18n = <?= json_encode([
        'loading' => $t('sys.imports_running', 'Running...'),
        'modelLoading' => $t('sys.ai_models_loading', 'Loading models...'),
        'modelLoaded' => $t('sys.ai_models_loaded', 'Models loaded'),
        'modelError' => $t('sys.ai_models_load_error', 'Failed to load models'),
        'testOk' => $t('sys.ai_connection_ok', 'Connection works.'),
        'testFailed' => $t('sys.ai_connection_failed', 'Connection test failed.'),
        'ajaxError' => $t('sys.ajax_error', 'AJAX error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const requestJson = async function (url, options) {
        const response = await fetch(url, Object.assign({
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        }, options || {}));
        const data = await response.json();
        return {response, data};
    };

    const renderAlert = function (target, type, text) {
        if (!target) {
            return;
        }
        target.className = 'alert alert-' + type + ' mb-0';
        target.textContent = text;
        target.classList.remove('d-none');
    };

    const updateDatalist = function (input, models) {
        const listId = input.getAttribute('data-ai-model-list');
        const datalist = listId ? document.getElementById(listId) : null;
        if (!datalist) {
            return;
        }
        datalist.innerHTML = '';
        (models || []).forEach(function (model) {
            if (!model || !model.id) {
                return;
            }
            const option = document.createElement('option');
            option.value = model.id;
            option.textContent = model.name || model.id;
            datalist.appendChild(option);
        });
    };

    const loadModels = async function (input) {
        const endpoint = form.getAttribute('data-ai-models-url') || '/admin/ajax_ai_models';
        const provider = input.getAttribute('data-ai-model-provider') || '';
        const query = input.value || '';
        const help = input.closest('.col-12, .col-lg-6')?.querySelector('[data-ai-model-help]');
        if (help) {
            help.textContent = i18n.modelLoading;
        }
        try {
            const url = endpoint + '&provider=' + encodeURIComponent(provider) + '&q=' + encodeURIComponent(query);
            const result = await requestJson(url);
            if (!result.data || !result.data.success) {
                throw new Error(result.data && result.data.message ? result.data.message : i18n.modelError);
            }
            updateDatalist(input, result.data.models || []);
            if (help) {
                help.textContent = i18n.modelLoaded + ': ' + (result.data.models || []).length;
            }
        } catch (error) {
            if (help) {
                help.textContent = error.message || i18n.modelError;
            }
        }
    };

    const bindModelSearch = function () {
        form.querySelectorAll('[data-ai-model-input]').forEach(function (input) {
            let timer = null;
            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(function () {
                    loadModels(input);
                }, 250);
            });
        });
        form.querySelectorAll('[data-ai-model-refresh]').forEach(function (button) {
            button.addEventListener('click', function () {
                const input = button.closest('.input-group')?.querySelector('[data-ai-model-input]');
                if (input) {
                    loadModels(input);
                    input.focus();
                }
            });
        });
    };

    const providerSelect = form.querySelector('[data-ai-provider-select]');
    const providerSettings = form.querySelector('[data-ai-provider-settings]');
    if (providerSelect && providerSettings) {
        providerSelect.addEventListener('change', async function () {
            const endpoint = form.getAttribute('data-ai-provider-settings-url') || '/admin/ajax_ai_provider_settings';
            providerSettings.classList.add('opacity-50');
            try {
                const result = await requestJson(endpoint + '&provider=' + encodeURIComponent(providerSelect.value));
                if (!result.data || !result.data.success) {
                    throw new Error(result.data && result.data.message ? result.data.message : i18n.ajaxError);
                }
                providerSettings.innerHTML = result.data.html || '';
                bindModelSearch();
            } catch (error) {
                providerSettings.innerHTML = '<div class="alert alert-warning mb-0"></div>';
                providerSettings.querySelector('.alert').textContent = error.message || i18n.ajaxError;
            } finally {
                providerSettings.classList.remove('opacity-50');
            }
        });
    }

    form.querySelectorAll('[data-ai-test-connection]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const resultBox = form.querySelector('[data-ai-test-result]');
            button.disabled = true;
            const original = button.innerHTML;
            button.textContent = i18n.loading;
            renderAlert(resultBox, 'info', i18n.loading);
            try {
                const result = await requestJson(button.getAttribute('data-ai-test-url') || '', {method: 'POST'});
                const message = result.data && result.data.message ? result.data.message : (result.response.ok ? i18n.testOk : i18n.testFailed);
                renderAlert(resultBox, result.response.ok && result.data.success ? 'success' : 'warning', message);
            } catch (error) {
                renderAlert(resultBox, 'danger', error.message || i18n.ajaxError);
            } finally {
                button.disabled = false;
                button.innerHTML = original;
            }
        });
    });

    bindModelSearch();
})();
</script>
