<?php

namespace custom\legal;

use classes\system\MarkdownRenderer;

class LegalDocumentService {

    private const DOCUMENTS = [
        'privacy_policy' => [
            'file' => 'privacy_policy.md',
            'title' => 'Политика в отношении обработки персональных данных',
            'version_constant' => 'ENV_LEGAL_PRIVACY_POLICY_VERSION',
            'route' => '/privacy_policy',
        ],
        'consent_personal_data' => [
            'file' => 'consent_personal_data.md',
            'title' => 'Согласие на обработку персональных данных',
            'version_constant' => 'ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION',
            'route' => '/consent_personal_data',
        ],
        'consent_personal_data_distribution' => [
            'file' => 'consent_personal_data_distribution.md',
            'title' => 'Согласие на обработку персональных данных, разрешённых для распространения',
            'version_constant' => 'ENV_LEGAL_PERSONAL_DATA_DISTRIBUTION_CONSENT_VERSION',
            'route' => '/consent_personal_data_distribution',
        ],
    ];

    public function getDocumentPath(string $slug): string {
        $document = self::DOCUMENTS[$slug] ?? null;
        if (!is_array($document)) {
            return '';
        }

        return ENV_CUSTOM_PATH . 'legal' . ENV_DIRSEP . (string) $document['file'];
    }

    public function getDocumentMeta(string $slug): array {
        $document = self::DOCUMENTS[$slug] ?? null;
        if (!is_array($document)) {
            return [];
        }

        return [
            'slug' => $slug,
            'title' => (string) $document['title'],
            'version' => $this->getConfigValue((string) $document['version_constant']),
            'canonical' => rtrim((string) ENV_URL_SITE, '/') . (string) $document['route'],
            'route' => (string) $document['route'],
        ];
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

        $documentBody = $this->stripRenderedPageHeader($this->replacePlaceholders($raw));

        return $this->relativizeInternalLinks(MarkdownRenderer::render($documentBody));
    }

    private function replacePlaceholders(string $raw): string {
        $operatorOgrn = $this->getConfigValue('ENV_LEGAL_OPERATOR_OGRN');
        $replacements = [
            '{{site_name}}' => (string) ENV_SITE_NAME,
            '{{site_url}}' => rtrim((string) ENV_URL_SITE, '/'),
            '{{site_email}}' => (string) ENV_SITE_EMAIL,
            '{{support_email}}' => defined('ENV_SUPPORT_EMAIL') ? (string) ENV_SUPPORT_EMAIL : (string) ENV_SITE_EMAIL,
            '{{operator_name}}' => $this->getConfigValue('ENV_LEGAL_OPERATOR_NAME', (string) ENV_SITE_NAME),
            '{{operator_status}}' => $this->getConfigValue('ENV_LEGAL_OPERATOR_STATUS', 'Оператор'),
            '{{operator_address}}' => $this->getConfigValue('ENV_LEGAL_OPERATOR_ADDRESS'),
            '{{operator_inn}}' => $this->getConfigValue('ENV_LEGAL_OPERATOR_INN'),
            '{{operator_ogrn}}' => $operatorOgrn,
            '{{operator_ogrn_line}}' => $this->buildOptionalOperatorLine('ОГРН/ОГРНИП', $operatorOgrn),
            '{{privacy_policy_version}}' => $this->getConfigValue('ENV_LEGAL_PRIVACY_POLICY_VERSION'),
            '{{personal_data_consent_version}}' => $this->getConfigValue('ENV_LEGAL_PERSONAL_DATA_CONSENT_VERSION'),
            '{{personal_data_distribution_consent_version}}' => $this->getConfigValue('ENV_LEGAL_PERSONAL_DATA_DISTRIBUTION_CONSENT_VERSION'),
        ];

        return strtr($raw, $replacements);
    }

    private function getConfigValue(string $constantName, string $default = ''): string {
        if (!defined($constantName)) {
            return $default;
        }

        return trim((string) constant($constantName));
    }

    private function buildOptionalOperatorLine(string $label, string $value): string {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, 'Укажите ')) {
            return $label . ': при наличии.';
        }

        return $label . ': **' . $value . '**.';
    }

    private function stripRenderedPageHeader(string $raw): string {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $lines = explode("\n", ltrim($raw));

        if (($lines[0] ?? '') !== '' && preg_match('/^#\s+/', (string) $lines[0])) {
            array_shift($lines);
        }

        while (array_key_exists(0, $lines) && trim((string) $lines[0]) === '') {
            array_shift($lines);
        }

        if (($lines[0] ?? '') !== '' && preg_match('/^Версия документа:\s*/ui', (string) $lines[0])) {
            array_shift($lines);
        }

        return trim(implode("\n", $lines));
    }

    private function relativizeInternalLinks(string $html): string {
        $siteUrl = rtrim((string) ENV_URL_SITE, '/');
        if ($siteUrl === '') {
            return $html;
        }

        $siteHost = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: '');
        if ($siteHost === '') {
            return $html;
        }

        $hostPattern = preg_quote($siteHost, '/');
        $html = preg_replace_callback(
            '/href=(["\'])https?:\/\/' . $hostPattern . '(\/[^"\']*)?\1/i',
            static function (array $matches): string {
                $quote = $matches[1];
                $path = $matches[2] ?? '/';
                return 'href=' . $quote . ($path !== '' ? $path : '/') . $quote;
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/>https?:\/\/' . $hostPattern . '(\/[^<\s]*)?</i',
            static function (array $matches): string {
                $path = $matches[1] ?? '/';
                return '>' . ($path !== '' ? $path : '/') . '<';
            },
            $html
        ) ?? $html;

        return $html;
    }
}
