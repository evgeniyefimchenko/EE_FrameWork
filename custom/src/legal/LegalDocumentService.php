<?php

namespace custom\legal;

use classes\system\MarkdownRenderer;

class LegalDocumentService {

    public function getDocumentPath(string $slug): string {
        $fileName = match ($slug) {
            'privacy_policy' => 'privacy_policy.md',
            'consent_personal_data' => 'consent_personal_data.md',
            default => '',
        };

        if ($fileName === '') {
            return '';
        }

        return ENV_CUSTOM_PATH . 'legal' . ENV_DIRSEP . $fileName;
    }

    public function getDocumentMeta(string $slug): array {
        return match ($slug) {
            'privacy_policy' => [
                'slug' => 'privacy_policy',
                'title' => 'Политика в отношении обработки персональных данных',
                'version' => defined('ENV_LEGAL_PRIVACY_POLICY_VERSION') ? (string) ENV_LEGAL_PRIVACY_POLICY_VERSION : '',
                'canonical' => ENV_URL_SITE . '/privacy_policy',
            ],
            'consent_personal_data' => [
                'slug' => 'consent_personal_data',
                'title' => 'Согласие на обработку персональных данных',
                'version' => defined('ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION') ? (string) ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION : '',
                'canonical' => ENV_URL_SITE . '/consent_personal_data',
            ],
            default => [],
        };
    }

    public function renderDocumentHtml(string $slug): string {
        $path = $this->getDocumentPath($slug);
        if ($path === '' || !is_readable($path)) {
            return '';
        }

        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return '';
        }

        return MarkdownRenderer::render($this->replacePlaceholders($raw));
    }

    private function replacePlaceholders(string $raw): string {
        $replacements = [
            '{{site_name}}' => (string) ENV_SITE_NAME,
            '{{site_url}}' => rtrim((string) ENV_URL_SITE, '/'),
            '{{site_email}}' => (string) ENV_SITE_EMAIL,
            '{{support_email}}' => defined('ENV_SUPPORT_EMAIL') ? (string) ENV_SUPPORT_EMAIL : (string) ENV_SITE_EMAIL,
            '{{operator_name}}' => defined('ENV_LEGAL_OPERATOR_NAME') ? (string) ENV_LEGAL_OPERATOR_NAME : (string) ENV_SITE_NAME,
            '{{operator_status}}' => defined('ENV_LEGAL_OPERATOR_STATUS') ? (string) ENV_LEGAL_OPERATOR_STATUS : 'Оператор',
            '{{operator_address}}' => defined('ENV_LEGAL_OPERATOR_ADDRESS') ? (string) ENV_LEGAL_OPERATOR_ADDRESS : '',
            '{{operator_inn}}' => defined('ENV_LEGAL_OPERATOR_INN') ? (string) ENV_LEGAL_OPERATOR_INN : '',
            '{{operator_ogrn}}' => defined('ENV_LEGAL_OPERATOR_OGRN') ? (string) ENV_LEGAL_OPERATOR_OGRN : '',
            '{{privacy_policy_version}}' => defined('ENV_LEGAL_PRIVACY_POLICY_VERSION') ? (string) ENV_LEGAL_PRIVACY_POLICY_VERSION : '',
            '{{personal_data_consent_version}}' => defined('ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION') ? (string) ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION : '',
        ];

        return strtr($raw, $replacements);
    }
}
