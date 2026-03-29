<?php

namespace classes\system;

use classes\plugins\SafeMySQL;

/**
 * Управляет стратегиями генерации slug для страниц и категорий.
 */
final class UrlPolicyService {

    private const DEFAULT_SETTINGS = [
        'source_mode' => 'title',
        'transliterate' => 1,
        'lowercase' => 1,
        'separator' => '-',
        'max_length' => 190,
        'stop_words' => [],
        'replace_map' => [],
        'fallback_slug' => 'item',
        'reserved_words_extra' => [],
        'dedupe_mode' => 'numeric_suffix',
    ];

    public static function getDefaultSettings(): array {
        return self::DEFAULT_SETTINGS;
    }

    public static function getPolicies(?string $entityType = null, bool $includeDisabled = true): array {
        self::assertInfrastructure();

        $sql = 'SELECT * FROM ?n';
        $params = [Constants::URL_POLICIES_TABLE];
        $where = [];
        if ($entityType !== null && self::normalizeEntityType($entityType) !== '') {
            $where[] = 'entity_type = ?s';
            $params[] = self::normalizeEntityType($entityType);
        }
        if (!$includeDisabled) {
            $where[] = "status != 'disabled'";
        }
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY entity_type ASC, is_default DESC, language_code ASC, name ASC, policy_id ASC';

        $rows = SafeMySQL::gi()->getAll($sql, ...$params);
        return array_values(array_filter(array_map([self::class, 'decoratePolicyRow'], is_array($rows) ? $rows : [])));
    }

    public static function getPolicy(int $policyId): ?array {
        self::assertInfrastructure();
        if ($policyId <= 0) {
            return null;
        }

        $row = SafeMySQL::gi()->getRow(
            'SELECT * FROM ?n WHERE policy_id = ?i LIMIT 1',
            Constants::URL_POLICIES_TABLE,
            $policyId
        );

        return is_array($row) ? self::decoratePolicyRow($row) : null;
    }

    public static function getPolicyOptions(?string $entityType = null): array {
        $options = [];
        foreach (self::getPolicies($entityType, false) as $policy) {
            if ((string) ($policy['status'] ?? 'active') !== 'active') {
                continue;
            }
            $policyId = (int) ($policy['policy_id'] ?? 0);
            if ($policyId <= 0) {
                continue;
            }
            $label = trim((string) ($policy['name'] ?? ('Policy #' . $policyId)));
            $meta = [];
            if (!empty($policy['entity_type'])) {
                $meta[] = (string) $policy['entity_type'];
            }
            if (!empty($policy['language_code'])) {
                $meta[] = (string) $policy['language_code'];
            } else {
                $meta[] = 'ALL';
            }
            if (!empty($policy['is_default'])) {
                $meta[] = 'default';
            }
            $options[] = [
                'policy_id' => $policyId,
                'label' => $label,
                'meta' => implode(' / ', $meta),
            ];
        }
        return $options;
    }

    public static function getDefaultPolicy(string $entityType, ?string $languageCode = null): ?array {
        return self::resolveEffectivePolicy($entityType, $languageCode, 0);
    }

    public static function resolveEffectivePolicy(string $entityType, ?string $languageCode = null, int $policyId = 0): array {
        $entityType = self::normalizeEntityType($entityType);
        $languageCode = self::normalizeLanguageCode($languageCode, true);
        if (!self::isInfrastructureInstalled()) {
            return self::decoratePolicyRow([
                'policy_id' => 0,
                'code' => 'inline-default-' . ($entityType !== '' ? $entityType : 'page'),
                'name' => 'Inline default',
                'entity_type' => $entityType !== '' ? $entityType : 'page',
                'language_code' => $languageCode,
                'status' => 'active',
                'is_default' => 1,
                'settings_json' => json_encode(self::DEFAULT_SETTINGS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'description' => '',
            ]);
        }

        if ($entityType === '') {
            return self::decoratePolicyRow([
                'policy_id' => 0,
                'code' => '',
                'name' => 'Inline default',
                'entity_type' => 'page',
                'language_code' => $languageCode,
                'status' => 'active',
                'is_default' => 1,
                'settings_json' => json_encode(self::DEFAULT_SETTINGS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'description' => '',
            ]);
        }

        if ($policyId > 0) {
            $policy = self::getPolicy($policyId);
            if ($policy !== null && (string) ($policy['status'] ?? 'active') !== 'disabled') {
                return $policy;
            }
        }

        $rows = SafeMySQL::gi()->getAll(
            "SELECT *
             FROM ?n
             WHERE entity_type = ?s
               AND status != 'disabled'
               AND (language_code = ?s OR language_code IS NULL OR language_code = '')
             ORDER BY
               CASE WHEN language_code = ?s THEN 2 ELSE 1 END DESC,
               is_default DESC,
               policy_id ASC",
            Constants::URL_POLICIES_TABLE,
            $entityType,
            $languageCode ?? '',
            $languageCode ?? ''
        );

        $row = is_array($rows) && !empty($rows[0]) ? $rows[0] : null;
        if (is_array($row)) {
            return self::decoratePolicyRow($row);
        }

        return self::decoratePolicyRow([
            'policy_id' => 0,
            'code' => 'inline-default-' . $entityType,
            'name' => 'Inline default',
            'entity_type' => $entityType,
            'language_code' => $languageCode,
            'status' => 'active',
            'is_default' => 1,
            'settings_json' => json_encode(self::DEFAULT_SETTINGS, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'description' => '',
        ]);
    }

    public static function savePolicy(array $data): OperationResult {
        self::assertInfrastructure();

        $policyId = (int) ($data['policy_id'] ?? 0);
        $entityType = self::normalizeEntityType((string) ($data['entity_type'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $code = trim((string) ($data['code'] ?? ''));
        $languageCode = self::normalizeLanguageCode((string) ($data['language_code'] ?? ''), true);
        $status = self::normalizeStatus((string) ($data['status'] ?? 'active'));
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        $description = trim((string) ($data['description'] ?? ''));

        if ($entityType === '') {
            return OperationResult::validation('Не указан тип сущности для URL-политики.');
        }
        if ($name === '') {
            return OperationResult::validation('Укажите название URL-политики.');
        }
        if ($code === '') {
            $code = self::slugifyCode($name, $entityType, $policyId);
        } else {
            $code = self::slugifyCode($code, $entityType, $policyId, false);
        }
        if ($code === '') {
            return OperationResult::validation('Не удалось сформировать код URL-политики.');
        }

        $settings = self::normalizeSettings($data['settings'] ?? $data);

        $existingPolicyId = (int) SafeMySQL::gi()->getOne(
            'SELECT policy_id FROM ?n WHERE code = ?s' . ($policyId > 0 ? ' AND policy_id != ?i' : '') . ' LIMIT 1',
            ...array_values(array_filter([
                Constants::URL_POLICIES_TABLE,
                $code,
                $policyId > 0 ? $policyId : null,
            ], static fn($value): bool => $value !== null))
        );
        if ($existingPolicyId > 0) {
            return OperationResult::validation('Политика с таким кодом уже существует.');
        }

        $payload = [
            'code' => $code,
            'name' => $name,
            'entity_type' => $entityType,
            'language_code' => $languageCode !== '' ? $languageCode : null,
            'status' => $status,
            'is_default' => $isDefault,
            'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'description' => $description !== '' ? $description : null,
        ];

        if ($policyId > 0) {
            SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE policy_id = ?i',
                Constants::URL_POLICIES_TABLE,
                $payload,
                $policyId
            );
        } else {
            SafeMySQL::gi()->query(
                'INSERT INTO ?n SET ?u',
                Constants::URL_POLICIES_TABLE,
                $payload
            );
            $policyId = (int) SafeMySQL::gi()->insertId();
        }

        if ($policyId <= 0) {
            return OperationResult::failure('Не удалось сохранить URL-политику.', 'url_policy_save_failed');
        }

        if ($isDefault > 0) {
            SafeMySQL::gi()->query(
                'UPDATE ?n
                 SET is_default = CASE WHEN policy_id = ?i THEN 1 ELSE 0 END
                 WHERE entity_type = ?s
                   AND (((?s = \'\') AND (language_code IS NULL OR language_code = \'\')) OR language_code = ?s)',
                Constants::URL_POLICIES_TABLE,
                $policyId,
                $entityType,
                $languageCode,
                $languageCode
            );
        }

        Router::clearRouteCache();
        return OperationResult::success(['policy_id' => $policyId], 'URL-политика сохранена.', $data['policy_id'] ?? 0 ? 'updated' : 'created');
    }

    public static function deletePolicy(int $policyId): OperationResult {
        self::assertInfrastructure();
        if ($policyId <= 0) {
            return OperationResult::validation('Не указан ID URL-политики.');
        }

        $policy = self::getPolicy($policyId);
        if ($policy === null) {
            return OperationResult::failure('URL-политика не найдена.', 'url_policy_not_found');
        }
        if (!empty($policy['is_default'])) {
            return OperationResult::validation('Нельзя удалить URL-политику по умолчанию.');
        }

        SafeMySQL::gi()->query(
            'DELETE FROM ?n WHERE policy_id = ?i',
            Constants::URL_POLICIES_TABLE,
            $policyId
        );
        Router::clearRouteCache();
        return OperationResult::success(['policy_id' => $policyId], 'URL-политика удалена.', 'deleted');
    }

    public static function applyPolicy(string $seed, array $policySettings, string $fallbackSlug = 'item'): string {
        $settings = self::normalizeSettings($policySettings);
        $value = trim($seed);
        if ($value === '') {
            return self::truncateSlug(trim((string) $settings['fallback_slug']) !== '' ? (string) $settings['fallback_slug'] : $fallbackSlug, $settings);
        }

        $replaceMap = (array) ($settings['replace_map'] ?? []);
        if ($replaceMap !== []) {
            $search = [];
            $replace = [];
            foreach ($replaceMap as $searchValue => $replaceValue) {
                $searchValue = (string) $searchValue;
                if ($searchValue === '') {
                    continue;
                }
                $search[] = $searchValue;
                $replace[] = (string) $replaceValue;
            }
            if ($search !== []) {
                $value = str_ireplace($search, $replace, $value);
            }
        }

        if (!empty($settings['transliterate']) && class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $value = (string) $transliterator->transliterate($value);
            }
        }

        if (!empty($settings['lowercase'])) {
            $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        }

        $separator = self::normalizeSeparator((string) ($settings['separator'] ?? '-'));
        $value = preg_replace('~[\'"`’]+~u', ' ', $value) ?? $value;
        $tokens = preg_split('~[^\p{L}\p{N}]+~u', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stopWords = self::normalizeWordList((array) ($settings['stop_words'] ?? []), !empty($settings['lowercase']));
        if ($stopWords !== []) {
            $tokens = array_values(array_filter($tokens, static function (string $token) use ($stopWords): bool {
                return !in_array($token, $stopWords, true);
            }));
        }

        $slug = implode($separator, $tokens);
        $slug = preg_replace('~' . preg_quote($separator, '~') . '{2,}~u', $separator, $slug) ?? $slug;
        $slug = trim((string) $slug, $separator);
        $slug = self::truncateSlug($slug, $settings);

        if ($slug === '') {
            $slug = self::truncateSlug((string) ($settings['fallback_slug'] ?? $fallbackSlug), $settings);
        }

        return $slug;
    }

    public static function getReservedWordsExtra(array $policy): array {
        $settings = self::normalizeSettings($policy['settings'] ?? $policy);
        return self::normalizeWordList((array) ($settings['reserved_words_extra'] ?? []), true);
    }

    public static function normalizeSettings(mixed $settings): array {
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            $settings = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($settings)) {
            $settings = [];
        }

        $normalized = self::DEFAULT_SETTINGS;
        $normalized['source_mode'] = self::normalizeSourceMode((string) ($settings['source_mode'] ?? $normalized['source_mode']));
        $normalized['transliterate'] = !empty($settings['transliterate']) ? 1 : 0;
        $normalized['lowercase'] = !empty($settings['lowercase']) ? 1 : 0;
        $normalized['separator'] = self::normalizeSeparator((string) ($settings['separator'] ?? $normalized['separator']));
        $normalized['max_length'] = max(10, min(190, (int) ($settings['max_length'] ?? $normalized['max_length'])));
        $normalized['stop_words'] = self::normalizeWordList($settings['stop_words'] ?? [], !empty($normalized['lowercase']));
        $normalized['replace_map'] = self::normalizeReplaceMap($settings['replace_map'] ?? []);
        $normalized['fallback_slug'] = trim((string) ($settings['fallback_slug'] ?? $normalized['fallback_slug'])) ?: 'item';
        $normalized['reserved_words_extra'] = self::normalizeWordList($settings['reserved_words_extra'] ?? [], true);
        $normalized['dedupe_mode'] = 'numeric_suffix';

        return $normalized;
    }

    private static function decoratePolicyRow(array $row): array {
        $row['policy_id'] = (int) ($row['policy_id'] ?? 0);
        $row['entity_type'] = self::normalizeEntityType((string) ($row['entity_type'] ?? ''));
        $row['language_code'] = self::normalizeLanguageCode((string) ($row['language_code'] ?? ''), true);
        $row['status'] = self::normalizeStatus((string) ($row['status'] ?? 'active'));
        $row['is_default'] = !empty($row['is_default']) ? 1 : 0;
        $row['settings'] = self::normalizeSettings($row['settings_json'] ?? []);
        return $row;
    }

    private static function normalizeEntityType(string $entityType): string {
        $entityType = strtolower(trim($entityType));
        return in_array($entityType, ['category', 'page'], true) ? $entityType : '';
    }

    private static function normalizeLanguageCode(?string $languageCode, bool $allowEmpty = false): string {
        $languageCode = strtoupper(trim((string) $languageCode));
        if ($languageCode === '' && !$allowEmpty) {
            return ee_get_default_content_lang_code();
        }
        return $languageCode;
    }

    private static function normalizeStatus(string $status): string {
        $status = strtolower(trim($status));
        return in_array($status, ['active', 'hidden', 'disabled'], true) ? $status : 'active';
    }

    private static function normalizeSourceMode(string $sourceMode): string {
        $sourceMode = strtolower(trim($sourceMode));
        return in_array($sourceMode, ['title', 'source_slug'], true) ? $sourceMode : 'title';
    }

    private static function normalizeSeparator(string $separator): string {
        $separator = trim($separator);
        if ($separator === '') {
            return '-';
        }
        return mb_substr($separator, 0, 1);
    }

    private static function normalizeWordList(mixed $value, bool $lowercase = true): array {
        if (is_string($value)) {
            $value = preg_split('~[\r\n,;]+~u', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            $result[] = $lowercase
                ? (function_exists('mb_strtolower') ? mb_strtolower($item) : strtolower($item))
                : $item;
        }

        return array_values(array_unique($result));
    }

    private static function normalizeReplaceMap(mixed $value): array {
        if (is_string($value)) {
            $lines = preg_split('~[\r\n]+~u', $value) ?: [];
            $mapped = [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || !str_contains($line, '=')) {
                    continue;
                }
                [$search, $replace] = array_pad(explode('=', $line, 2), 2, '');
                $search = trim((string) $search);
                if ($search === '') {
                    continue;
                }
                $mapped[$search] = trim((string) $replace);
            }
            return $mapped;
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $search => $replace) {
            $search = trim((string) $search);
            if ($search === '') {
                continue;
            }
            $result[$search] = trim((string) $replace);
        }
        return $result;
    }

    private static function truncateSlug(string $slug, array $settings): string {
        $maxLength = max(10, min(190, (int) ($settings['max_length'] ?? 190)));
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }
        return mb_substr($slug, 0, $maxLength);
    }

    private static function slugifyCode(string $value, string $entityType, int $excludePolicyId = 0, bool $appendEntityType = true): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
            if ($transliterator) {
                $value = (string) $transliterator->transliterate($value);
            }
        }
        $value = function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        if ($value === '') {
            $value = 'policy';
        }
        if ($appendEntityType && !str_contains($value, $entityType)) {
            $value = $entityType . '-' . $value;
        }
        $baseValue = mb_substr($value, 0, 90);
        $candidate = $baseValue;
        $suffix = 2;
        while (self::codeExists($candidate, $excludePolicyId)) {
            $candidate = rtrim(mb_substr($baseValue, 0, max(1, 90 - strlen((string) $suffix) - 1)), '-') . '-' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private static function codeExists(string $code, int $excludePolicyId = 0): bool {
        $sql = 'SELECT policy_id FROM ?n WHERE code = ?s';
        $params = [Constants::URL_POLICIES_TABLE, $code];
        if ($excludePolicyId > 0) {
            $sql .= ' AND policy_id != ?i';
            $params[] = $excludePolicyId;
        }
        $sql .= ' LIMIT 1';
        return (int) SafeMySQL::gi()->getOne($sql, ...$params) > 0;
    }

    private static function assertInfrastructure(): void {
        if (self::isInfrastructureInstalled()) {
            return;
        }
        throw new \RuntimeException('URL policy infrastructure is not installed. Run install/upgrade first.');
    }

    private static function isInfrastructureInstalled(): bool {
        $exists = (int) SafeMySQL::gi()->getOne(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?s',
            Constants::URL_POLICIES_TABLE
        );
        return $exists > 0;
    }
}
