<?php

namespace app\admin;

use classes\system\Constants;
use classes\system\SysClass;

/**
 * Управление URL-политиками и редиректами.
 */
trait UrlManagementTrait {

    public function url_policies($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/url_policies',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $this->getStandardViews();
        $this->view->set('url_policies', $this->models['m_url_management']->getPolicies());
        $this->view->set('body_view', $this->view->read('v_url_policies'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = $this->lang['sys.url_policies'] ?? 'URL-политики';
        $this->showLayout($this->parameters_layout);
    }

    public function url_policy_edit($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/url_policy_edit',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $policyId = $this->extractUrlManagementIdFromParams($params);
        $policy = $policyId > 0
            ? $this->models['m_url_management']->getPolicy($policyId)
            : $this->models['m_url_management']->getPolicyDefaults((string) ($_GET['entity_type'] ?? 'page'));

        if ($policyId > 0 && !$policy) {
            $this->notifyOperationResult(false, [
                'default_error_message' => $this->lang['sys.url_policy_not_found'] ?? 'URL-политика не найдена.',
            ]);
            SysClass::handleRedirect(200, '/admin/url_policies');
            return;
        }

        if (!empty($_POST)) {
            $result = $this->models['m_url_management']->savePolicy([
                'policy_id' => $policyId,
                'code' => trim((string) ($_POST['code'] ?? '')),
                'name' => trim((string) ($_POST['name'] ?? '')),
                'entity_type' => trim((string) ($_POST['entity_type'] ?? 'page')),
                'language_code' => trim((string) ($_POST['language_code'] ?? '')),
                'status' => trim((string) ($_POST['status'] ?? 'active')),
                'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                'description' => trim((string) ($_POST['description'] ?? '')),
                'settings' => [
                    'source_mode' => trim((string) ($_POST['source_mode'] ?? 'title')),
                    'transliterate' => !empty($_POST['transliterate']) ? 1 : 0,
                    'lowercase' => !empty($_POST['lowercase']) ? 1 : 0,
                    'separator' => trim((string) ($_POST['separator'] ?? '-')),
                    'max_length' => (int) ($_POST['max_length'] ?? 190),
                    'stop_words' => trim((string) ($_POST['stop_words'] ?? '')),
                    'replace_map' => trim((string) ($_POST['replace_map'] ?? '')),
                    'fallback_slug' => trim((string) ($_POST['fallback_slug'] ?? 'item')),
                    'reserved_words_extra' => trim((string) ($_POST['reserved_words_extra'] ?? '')),
                ],
            ]);

            $this->notifyOperationResult(
                $result,
                [
                    'success_message' => $this->lang['sys.url_policy_saved'] ?? 'URL-политика сохранена.',
                    'default_error_message' => $this->lang['sys.data_update_error'] ?? 'Ошибка сохранения данных.',
                ]
            );

            if ($result->isSuccess()) {
                $savedPolicyId = $result->getId(['policy_id']);
                SysClass::handleRedirect(200, '/admin/url_policy_edit/id/' . $savedPolicyId);
                return;
            }

            $policy = array_merge(
                $this->models['m_url_management']->getPolicyDefaults((string) ($_POST['entity_type'] ?? 'page')),
                [
                    'policy_id' => $policyId,
                    'code' => trim((string) ($_POST['code'] ?? '')),
                    'name' => trim((string) ($_POST['name'] ?? '')),
                    'entity_type' => trim((string) ($_POST['entity_type'] ?? 'page')),
                    'language_code' => trim((string) ($_POST['language_code'] ?? '')),
                    'status' => trim((string) ($_POST['status'] ?? 'active')),
                    'is_default' => !empty($_POST['is_default']) ? 1 : 0,
                    'description' => trim((string) ($_POST['description'] ?? '')),
                    'settings' => [
                        'source_mode' => trim((string) ($_POST['source_mode'] ?? 'title')),
                        'transliterate' => !empty($_POST['transliterate']) ? 1 : 0,
                        'lowercase' => !empty($_POST['lowercase']) ? 1 : 0,
                        'separator' => trim((string) ($_POST['separator'] ?? '-')),
                        'max_length' => (int) ($_POST['max_length'] ?? 190),
                        'stop_words' => trim((string) ($_POST['stop_words'] ?? '')),
                        'replace_map' => trim((string) ($_POST['replace_map'] ?? '')),
                        'fallback_slug' => trim((string) ($_POST['fallback_slug'] ?? 'item')),
                        'reserved_words_extra' => trim((string) ($_POST['reserved_words_extra'] ?? '')),
                    ],
                ]
            );
        }

        $this->getStandardViews();
        $this->view->set('url_policy', $policy);
        $this->view->set('body_view', $this->view->read('v_edit_url_policy'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = $policyId > 0
            ? ($this->lang['sys.url_policy_edit'] ?? 'Редактирование URL-политики')
            : ($this->lang['sys.url_policy_new'] ?? 'Новая URL-политика');
        $this->showLayout($this->parameters_layout);
    }

    public function delete_url_policy($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/url_policies',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $policyId = $this->extractUrlManagementIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_url_management']->deletePolicy($policyId),
            [
                'success_message' => $this->lang['sys.url_policy_deleted'] ?? 'URL-политика удалена.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/url_policies');
    }

    public function redirects($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/redirects',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $this->getStandardViews();
        $this->view->set('redirects_list', $this->models['m_url_management']->getRedirects(500));
        $this->view->set('body_view', $this->view->read('v_redirects'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = $this->lang['sys.redirects'] ?? 'Редиректы';
        $this->showLayout($this->parameters_layout);
    }

    public function redirect_edit($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/redirect_edit',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $redirectId = $this->extractUrlManagementIdFromParams($params);
        $redirect = $redirectId > 0
            ? $this->models['m_url_management']->getRedirect($redirectId)
            : $this->models['m_url_management']->getRedirectDefaults();

        if ($redirectId > 0 && !$redirect) {
            $this->notifyOperationResult(false, [
                'default_error_message' => $this->lang['sys.redirect_not_found'] ?? 'Редирект не найден.',
            ]);
            SysClass::handleRedirect(200, '/admin/redirects');
            return;
        }

        if (!empty($_POST)) {
            $result = $this->models['m_url_management']->saveRedirect([
                'redirect_id' => $redirectId,
                'source_host' => trim((string) ($_POST['source_host'] ?? '')),
                'source_path' => trim((string) ($_POST['source_path'] ?? '')),
                'language_code' => trim((string) ($_POST['language_code'] ?? '')),
                'target_type' => trim((string) ($_POST['target_type'] ?? 'path')),
                'target_path' => trim((string) ($_POST['target_path'] ?? '')),
                'target_entity_type' => trim((string) ($_POST['target_entity_type'] ?? 'page')),
                'target_entity_id' => (int) ($_POST['target_entity_id'] ?? 0),
                'http_code' => (int) ($_POST['http_code'] ?? 301),
                'status' => trim((string) ($_POST['status'] ?? 'active')),
                'is_auto' => !empty($_POST['is_auto']) ? 1 : 0,
                'note' => trim((string) ($_POST['note'] ?? '')),
            ], trim((string) ($_POST['conflict_policy'] ?? 'skip_existing')));

            $this->notifyOperationResult(
                $result,
                [
                    'success_message' => $this->lang['sys.redirect_saved'] ?? 'Редирект сохранён.',
                    'default_error_message' => $this->lang['sys.data_update_error'] ?? 'Ошибка сохранения данных.',
                ]
            );

            if ($result->isSuccess()) {
                $savedRedirectId = $result->getId(['redirect_id']);
                SysClass::handleRedirect(200, '/admin/redirect_edit/id/' . $savedRedirectId);
                return;
            }

            $redirect = array_merge(
                $this->models['m_url_management']->getRedirectDefaults(),
                [
                    'redirect_id' => $redirectId,
                    'source_host' => trim((string) ($_POST['source_host'] ?? '')),
                    'source_path' => trim((string) ($_POST['source_path'] ?? '')),
                    'language_code' => trim((string) ($_POST['language_code'] ?? '')),
                    'target_type' => trim((string) ($_POST['target_type'] ?? 'path')),
                    'target_path' => trim((string) ($_POST['target_path'] ?? '')),
                    'target_entity_type' => trim((string) ($_POST['target_entity_type'] ?? 'page')),
                    'target_entity_id' => (int) ($_POST['target_entity_id'] ?? 0),
                    'http_code' => (int) ($_POST['http_code'] ?? 301),
                    'status' => trim((string) ($_POST['status'] ?? 'active')),
                    'is_auto' => !empty($_POST['is_auto']) ? 1 : 0,
                    'note' => trim((string) ($_POST['note'] ?? '')),
                ]
            );
        }

        $this->getStandardViews();
        $this->view->set('redirect_item', $redirect);
        $this->view->set('body_view', $this->view->read('v_edit_redirect'));
        $this->html = $this->view->read('v_dashboard');
        $this->parameters_layout['layout_content'] = $this->html;
        $this->parameters_layout['layout'] = 'dashboard';
        $this->parameters_layout['title'] = $redirectId > 0
            ? ($this->lang['sys.redirect_edit'] ?? 'Редактирование редиректа')
            : ($this->lang['sys.redirect_new'] ?? 'Новый редирект');
        $this->showLayout($this->parameters_layout);
    }

    public function delete_redirect($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/redirects',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $redirectId = $this->extractUrlManagementIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_url_management']->deleteRedirect($redirectId),
            [
                'success_message' => $this->lang['sys.redirect_deleted'] ?? 'Редирект удалён.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/redirects');
    }

    public function toggle_redirect($params = []): void {
        if (!$this->requireAccess([Constants::ADMIN], [
            'return' => 'admin/redirects',
            'initiator' => __METHOD__,
        ])) {
            return;
        }

        $this->loadModel('m_url_management');
        $redirectId = $this->extractUrlManagementIdFromParams($params);
        $this->notifyOperationResult(
            $this->models['m_url_management']->toggleRedirect($redirectId),
            [
                'success_message' => $this->lang['sys.redirect_saved'] ?? 'Редирект обновлён.',
                'default_error_message' => $this->lang['sys.error'] ?? 'Ошибка',
            ]
        );
        SysClass::handleRedirect(200, '/admin/redirects');
    }

    private function extractUrlManagementIdFromParams(array $params): int {
        if (in_array('id', $params, true)) {
            $index = array_search('id', $params, true);
            if ($index !== false && isset($params[$index + 1])) {
                return (int) $params[$index + 1];
            }
        }
        return 0;
    }
}
