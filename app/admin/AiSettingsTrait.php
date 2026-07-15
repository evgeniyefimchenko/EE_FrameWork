<?php

namespace app\admin;

use classes\system\Constants;
use classes\system\CsrfService;
use classes\system\SysClass;

trait AiSettingsTrait {

    public function ai_profiles(array $params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/ai_profiles',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_ai_settings');
        $this->models['m_ai_settings']->ensureInfrastructure();
        $this->renderAiSettingsAdminPage('v_ai_profiles', [
            'ai_profiles' => $this->models['m_ai_settings']->getProfiles(),
            'ai_notice' => $this->resolveAiSettingsNotice(),
            'ai_active_page' => 'profiles',
        ], $this->tAi('sys.ai_profiles', 'AI profiles'));
    }

    public function ai_profile(array $params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/ai_profiles',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_ai_settings');
        $model = $this->models['m_ai_settings'];
        $model->ensureInfrastructure();
        $profileId = $this->resolveAiProfileId($params);
        $notice = $this->resolveAiSettingsNotice();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!CsrfService::isValidForCurrentRequest()) {
                $notice = [
                    'type' => 'danger',
                    'text' => $this->tAi('sys.security_action_expired', 'Security check failed. Please repeat the action.'),
                ];
            } else {
                try {
                    $savedId = $model->saveProfile($_POST, $profileId);
                    SysClass::handleRedirect(200, '/admin/ai_profile/id/' . $savedId . '?status=saved');
                    return;
                } catch (\Throwable $e) {
                    $notice = ['type' => 'danger', 'text' => $this->translateAiException($e)];
                }
            }
        }

        $profile = $profileId > 0 ? $model->getProfileById($profileId) : null;
        $profile = is_array($profile) ? $profile : $model->getDefaultProfile();
        $providerContext = $model->getProviderSettingsContext((string) ($profile['provider'] ?? ''), $profile);
        $modelOptions = $model->searchProviderModels(
            (string) ($providerContext['provider'] ?? ''),
            $profileId,
            (string) ($profile['model'] ?? ''),
            25
        );

        $this->renderAiSettingsAdminPage('v_ai_profile', [
            'ai_profile' => $profile,
            'ai_profile_is_new' => $profileId <= 0,
            'ai_provider_options' => $model->getProviderOptions(),
            'ai_provider_settings_context' => $providerContext,
            'ai_model_options' => $modelOptions,
            'ai_notice' => $notice,
            'ai_active_page' => 'profiles',
        ], $profileId > 0 ? $this->tAi('sys.ai_profile', 'AI profile') : $this->tAi('sys.ai_profile_new', 'New AI profile'));
    }

    public function ai_statistics(array $params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/ai_statistics',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_ai_settings');
        $this->models['m_ai_settings']->ensureInfrastructure();
        $this->renderAiSettingsAdminPage('v_ai_statistics', [
            'ai_profile_stats' => $this->models['m_ai_settings']->getProfileStats(),
            'ai_active_page' => 'statistics',
        ], $this->tAi('sys.ai_statistics', 'AI statistics'));
    }

    public function ajax_ai_provider_settings(array $params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => true,
            'return' => 'admin/ai_profiles',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_ai_settings');
        $model = $this->models['m_ai_settings'];
        $profileId = max(0, (int) ($_GET['profile_id'] ?? $_GET['id'] ?? 0));
        $profile = $profileId > 0 ? $model->getProfileById($profileId) : null;
        $profile = is_array($profile) ? $profile : $model->getDefaultProfile();
        $profile['provider'] = (string) ($_GET['provider'] ?? $profile['provider'] ?? '');
        $context = $model->getProviderSettingsContext((string) $profile['provider'], $profile);

        $this->view->set('ai_profile', $profile);
        $this->view->set('ai_profile_is_new', $profileId <= 0);
        $this->view->set('ai_provider_settings_context', $context);
        $this->view->set('ai_model_options', $model->searchProviderModels((string) ($context['provider'] ?? ''), $profileId, (string) ($profile['model'] ?? ''), 25));

        $this->sendAiJson([
            'success' => true,
            'provider' => $context['provider'] ?? '',
            'html' => $this->view->read('v_ai_provider_settings'),
        ]);
    }

    public function ajax_ai_models(array $params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => true,
            'return' => 'admin/ai_profiles',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_ai_settings');
        $models = $this->models['m_ai_settings']->searchProviderModels(
            (string) ($_GET['provider'] ?? ''),
            max(0, (int) ($_GET['profile_id'] ?? 0)),
            (string) ($_GET['q'] ?? ''),
            50
        );

        $this->sendAiJson([
            'success' => true,
            'models' => $models,
        ]);
    }

    public function ajax_ai_profile_test(array $params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'ajax' => true,
            'return' => 'admin/ai_profiles',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST' || !CsrfService::isValidForCurrentRequest()) {
            $this->sendAiJson([
                'success' => false,
                'message' => $this->tAi('sys.security_action_expired', 'Security check failed. Please repeat the action.'),
            ], 400);
            return;
        }

        $this->loadModel('m_ai_settings');
        $profileId = $this->resolveAiProfileId($params);
        $result = $this->models['m_ai_settings']->testConnection($profileId);
        $message = $this->translateAiProviderMessage((string) ($result['error_code'] ?? ''), (string) ($result['message'] ?? ''));

        $this->sendAiJson([
            'success' => !empty($result['ok']),
            'message' => $message,
            'http_code' => (int) ($result['http_code'] ?? 0),
            'models_count' => (int) ($result['models_count'] ?? 0),
        ], !empty($result['ok']) ? 200 : 422);
    }

    private function resolveAiProfileId(array $params): int {
        foreach ($params as $index => $value) {
            if ((string) $value === 'id' && isset($params[$index + 1])) {
                return max(0, (int) $params[$index + 1]);
            }
        }

        return max(0, (int) ($_GET['id'] ?? $_POST['id'] ?? 0));
    }

    private function renderAiSettingsAdminPage(string $viewName, array $vars, string $title): void {
        foreach ($vars as $key => $value) {
            $this->view->set($key, $value);
        }
        $this->getStandardViews();
        $this->view->set('body_view', $this->view->read($viewName));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = ENV_SITE_NAME . ' - ' . $title;
        $this->parameters_layout['canonical_href'] = ENV_URL_SITE . '/' . trim((string) ($_GET['route'] ?? ''), '/');
        $this->parameters_layout['keywords'] = SysClass::getKeywordsFromText($this->html);
        $this->showLayout($this->parameters_layout);
    }

    private function resolveAiSettingsNotice(): ?array {
        return match (trim((string) ($_GET['status'] ?? ''))) {
            'saved' => ['type' => 'success', 'text' => $this->tAi('sys.ai_profile_saved', 'AI profile saved.')],
            default => null,
        };
    }

    private function translateAiException(\Throwable $e): string {
        $message = trim((string) $e->getMessage());
        if (str_starts_with($message, 'sys.')) {
            return $this->tAi($message, $message);
        }
        return mb_substr($message, 0, 300, 'UTF-8');
    }

    private function translateAiProviderMessage(string $code, string $fallback): string {
        return match ($code) {
            'ok' => $this->tAi('sys.ai_connection_ok_models', 'Connection OK. Models found:') . ' ' . preg_replace('~\D+~', '', $fallback),
            'api_key_missing' => $this->tAi('sys.ai_api_key_missing', 'API key is not configured.'),
            'profile_not_found' => $this->tAi('sys.ai_profile_not_found', 'AI profile was not found.'),
            'curl_missing' => $this->tAi('sys.ai_curl_missing', 'curl extension is not available.'),
            'invalid_base_url' => $this->tAi('sys.ai_api_base_url_invalid', 'API base URL must start with http:// or https://.'),
            default => $fallback !== '' ? mb_substr($fallback, 0, 300, 'UTF-8') : $this->tAi('sys.ai_connection_failed', 'Connection test failed.'),
        };
    }

    private function tAi(string $key, string $fallback): string {
        return (string) ($this->lang[$key] ?? $fallback);
    }

    private function sendAiJson(array $payload, int $statusCode = 200): void {
        http_response_code($statusCode);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
