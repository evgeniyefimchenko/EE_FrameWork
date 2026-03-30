<?php

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\EntitySlugService;
use classes\system\EntityTranslationService;
use classes\system\SysClass;
use classes\system\Hook;
use classes\system\Logger;
use classes\system\OperationResult;

/**
 * РњРѕРґРµР»СЊ СЂР°Р±РѕС‚С‹ СЃ СЃС‚СЂР°РЅРёС†Р°РјРё
 */
class ModelPages {

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ РІСЃРµС… СЃС‚СЂР°РЅРёС† СЃ РІРѕР·РјРѕР¶РЅРѕСЃС‚СЊСЋ СЃРѕСЂС‚РёСЂРѕРІРєРё, С„РёР»СЊС‚СЂР°С†РёРё Рё РѕРіСЂР°РЅРёС‡РµРЅРёСЏ РєРѕР»РёС‡РµСЃС‚РІР° Р·Р°РїРёСЃРµР№
     * @param string $order РЎС‚СЂРѕРєР° СЃ СЃРѕСЂС‚РёСЂРѕРІРєРѕР№ (РЅР°РїСЂРёРјРµСЂ, 'page_id ASC')
     * @param string|null $where РЈСЃР»РѕРІРёРµ РґР»СЏ С„РёР»СЊС‚СЂР°С†РёРё РґР°РЅРЅС‹С… (РѕРїС†РёРѕРЅР°Р»СЊРЅРѕ)
     * @param int $start РќР°С‡Р°Р»СЊРЅР°СЏ РїРѕР·РёС†РёСЏ РґР»СЏ РІС‹Р±РѕСЂРєРё (РѕРїС†РёРѕРЅР°Р»СЊРЅРѕ)
     * @param int $limit РљРѕР»РёС‡РµСЃС‚РІРѕ Р·Р°РїРёСЃРµР№ РґР»СЏ РІС‹Р±РѕСЂРєРё (РѕРїС†РёРѕРЅР°Р»СЊРЅРѕ)
     * @param string $language_code РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return array РњР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё СЃС‚СЂР°РЅРёС†
     */
    public function getPagesData($order = 'page_id ASC', $where = null, $start = 0, $limit = 100, $language_code = ENV_DEF_LANG) {
        $start = $start ? $start : 0;
        $order = is_string($order) ? trim($order) : '';
        $where = is_string($where) ? trim($where) : '';
        // РџСЂРѕРІРµСЂРєР°, СЃРѕРґРµСЂР¶РёС‚ Р»Рё $where РёР»Рё $order type_id
        $needsJoin = str_contains($where, 'type_id') || str_contains($order, 'type_id');
        $languageCondition = "language_code = ?s";
        if ($where) {
            $where = "($where) AND $languageCondition";
        } else {
            $where = $languageCondition;
        }
        if ($needsJoin) {
            // Р•СЃР»Рё type_id РїСЂРёСЃСѓС‚СЃС‚РІСѓРµС‚ РІ $where РёР»Рё $order, РїСЂРёРјРµРЅСЏРµРј JOIN
            $order = SysClass::ee_addPrefixToFields($order, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE), 'e.');
            $where = SysClass::ee_addPrefixToFields($where, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE), 'e.');
            $order = str_replace('type_id', 't.type_id', $order);
            $where = str_replace('type_id', 't.type_id', $where);
            $sql_pages = "
            SELECT e.page_id
            FROM ?n AS e
            LEFT JOIN ?n AS c ON e.category_id = c.category_id
            LEFT JOIN ?n AS t ON c.type_id = t.type_id
            WHERE $where
            ORDER BY $order
            LIMIT ?i, ?i";
            $res_array = SafeMySQL::gi()->getAll($sql_pages, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, $language_code, $start, $limit);
        } else {
            // Р•СЃР»Рё type_id РѕС‚СЃСѓС‚СЃС‚РІСѓРµС‚, РїСЂРёРјРµРЅСЏРµРј РїСЂРѕСЃС‚РѕР№ Р·Р°РїСЂРѕСЃ
            $normalizedWhere = preg_replace('/\b[a-zA-Z_][a-zA-Z0-9_]*\./', '', $where) ?: $where;
            $orderString = $order ? (preg_replace('/\b[a-zA-Z_][a-zA-Z0-9_]*\./', '', $order) ?: $order) : 'page_id ASC';
            $sql_pages = "SELECT e.page_id FROM ?n as e WHERE $normalizedWhere ORDER BY $orderString LIMIT ?i, ?i";
            $res_array = SafeMySQL::gi()->getAll($sql_pages, Constants::PAGES_TABLE, $language_code, $start, $limit);
        }
        $res = [];
        foreach ($res_array as $page) {
            $res[] = $this->getPageData($page['page_id'], $language_code);
        }
        if ($needsJoin) {
            $sql_count = "
            SELECT COUNT(*) as total_count
            FROM ?n AS e
            LEFT JOIN ?n AS c ON e.category_id = c.category_id
            LEFT JOIN ?n AS t ON c.type_id = t.type_id
            WHERE $where";
            $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PAGES_TABLE, Constants::CATEGORIES_TABLE, Constants::CATEGORIES_TYPES_TABLE, $language_code);
        } else {
            $normalizedWhere = preg_replace('/\b[a-zA-Z_][a-zA-Z0-9_]*\./', '', $where) ?: $where;
            $sql_count = "SELECT COUNT(*) as total_count FROM ?n WHERE $normalizedWhere";
            $total_count = SafeMySQL::gi()->getOne($sql_count, Constants::PAGES_TABLE, $language_code);
        }
        return [
            'data' => $res,
            'total_count' => $total_count
        ];
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РґР°РЅРЅС‹Рµ РѕРґРЅРѕР№ СЃС‚СЂР°РЅРёС†С‹ РїРѕ РµС‘ ID
     * @param int $pageId ID СЃС‚СЂР°РЅРёС†С‹, РґР»СЏ РєРѕС‚РѕСЂРѕР№ РЅСѓР¶РЅРѕ РїРѕР»СѓС‡РёС‚СЊ РґР°РЅРЅС‹Рµ
     * @param string $language_code РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @param string $status РЎС‚Р°С‚СѓСЃ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° Constants::ALL_STATUS
     * @return array|null РњР°СЃСЃРёРІ СЃ РґР°РЅРЅС‹РјРё СЃС‚СЂР°РЅРёС†С‹ РёР»Рё NULL, РµСЃР»Рё СЃС‚СЂР°РЅРёС†Р° РЅРµ РЅР°Р№РґРµРЅР°
     */
    public function getPageData($pageId, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $sql_page = "
        SELECT e.*, c.title as category_title, t.name as type_name 
        FROM ?n AS e 
        LEFT JOIN ?n AS c ON e.category_id = c.category_id AND c.language_code = ?s
        LEFT JOIN ?n AS t ON c.type_id = t.type_id AND t.language_code = ?s
        WHERE e.status IN (?a) AND e.page_id = ?i AND e.language_code = ?s";
        $pageData = SafeMySQL::gi()->getRow(
                $sql_page,
                Constants::PAGES_TABLE,
                Constants::CATEGORIES_TABLE,
                $language_code,
                Constants::CATEGORIES_TYPES_TABLE,
                $language_code,
                $status,
                $pageId,
                $language_code
        );
        if (!$pageData) {
            return null;
        }
        return $pageData;
    }

    /**
     * РџРѕР»СѓС‡Р°РµС‚ РІСЃРµ СЃС‚СЂР°РЅРёС†С‹, РёСЃРєР»СЋС‡Р°СЏ РѕРґРЅСѓ РїРѕ РµС‘ ID
     * @param int|null $excludePageId ID СЃС‚СЂР°РЅРёС†С‹ РґР»СЏ РёСЃРєР»СЋС‡РµРЅРёСЏ РёР· СЂРµР·СѓР»СЊС‚Р°С‚РѕРІ (РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ NULL)
     * @param string $language_code РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @param string $status РЎС‚Р°С‚СѓСЃ С‚РёРїР° СЃРІРѕР№СЃС‚РІР° Constants::ALL_STATUS
     * @return array РњР°СЃСЃРёРІ Р°СЃСЃРѕС†РёР°С‚РёРІРЅС‹С… РјР°СЃСЃРёРІРѕРІ, РєР°Р¶РґС‹Р№ РёР· РєРѕС‚РѕСЂС‹С… СЃРѕРґРµСЂР¶РёС‚ ID Рё Р·Р°РіРѕР»РѕРІРѕРє СЃС‚СЂР°РЅРёС†С‹. РџРµСЂРІС‹Р№ СЌР»РµРјРµРЅС‚ РјР°СЃСЃРёРІР° РІСЃРµРіРґР° РёРјРµРµС‚ page_id 0 Рё РїСѓСЃС‚РѕР№ Р·Р°РіРѕР»РѕРІРѕРє
     */
    public function getAllPages($excludePageId = null, $language_code = ENV_DEF_LANG, $status = Constants::ALL_STATUS) {
        if (is_string($status)) {
            $status = [$status];
        } else if (!is_array($status)) {
            $status = Constants::ALL_STATUS;
        }
        $add_query = '';
        if (is_numeric($excludePageId)) {
            $add_query = ' AND page_id != ' . $excludePageId;
        }
        $sql_pages = "SELECT page_id, title FROM ?n WHERE status IN (?a) AND language_code = ?s" . $add_query;
        $res = SafeMySQL::gi()->getAll($sql_pages, Constants::PAGES_TABLE, $status, $language_code);   
        return $res;
    }

    /**
     * Р’РѕР·РІСЂР°С‰Р°РµС‚ Р·Р°РіРѕР»РѕРІРѕРє СЃС‚СЂР°РЅРёС†С‹ РїРѕ РµС‘ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂСѓ Рё РєРѕРґСѓ СЏР·С‹РєР°
     * @param int $pageId РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃС‚СЂР°РЅРёС†С‹
     * @param string $languageCode РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return string|null Р’РѕР·РІСЂР°С‰Р°РµС‚ Р·Р°РіРѕР»РѕРІРѕРє СЃС‚СЂР°РЅРёС†С‹ РёР»Рё null, РµСЃР»Рё СЃС‚СЂР°РЅРёС†Р° РЅРµ РЅР°Р№РґРµРЅР°
     */
    public function getPageTitleById($pageId, $languageCode = ENV_DEF_LANG) {
        if (empty($pageId) || !is_numeric($pageId)) {
            return null; // Р•СЃР»Рё РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃС‚СЂР°РЅРёС†С‹ РЅРµРєРѕСЂСЂРµРєС‚РµРЅ, РІРѕР·РІСЂР°С‰Р°РµРј null
        }

        // Р’С‹РїРѕР»РЅСЏРµРј Р·Р°РїСЂРѕСЃ Рє Р±Р°Р·Рµ РґР°РЅРЅС‹С… РґР»СЏ РїРѕР»СѓС‡РµРЅРёСЏ Р·Р°РіРѕР»РѕРІРєР°
        $title = SafeMySQL::gi()->getOne(
                'SELECT title FROM ?n WHERE page_id = ?i AND language_code = ?s',
                Constants::PAGES_TABLE,
                $pageId,
                $languageCode
        );

        return $title ?: null; // Р’РѕР·РІСЂР°С‰Р°РµРј null, РµСЃР»Рё Р·Р°РіРѕР»РѕРІРѕРє РЅРµ РЅР°Р№РґРµРЅ
    }

    /**
     * РћР±РЅРѕРІР»СЏРµС‚ РґР°РЅРЅС‹Рµ СЃС‚СЂР°РЅРёС†С‹
     * @param array $pageData РњР°СЃСЃРёРІ РґР°РЅРЅС‹С… СЃС‚СЂР°РЅРёС†С‹
     * @param string $language_code РљРѕРґ СЏР·С‹РєР° РїРѕ СЃС‚Р°РЅРґР°СЂС‚Сѓ ISO 3166-2. РџРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РёСЃРїРѕР»СЊР·СѓРµС‚СЃСЏ Р·РЅР°С‡РµРЅРёРµ РёР· РєРѕРЅСЃС‚Р°РЅС‚С‹ ENV_DEF_LANG
     * @return int|bool Р’РѕР·РІСЂР°С‰Р°РµС‚ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ РѕР±РЅРѕРІР»РµРЅРЅРѕР№ СЃС‚СЂР°РЅРёС†С‹ РІ СЃР»СѓС‡Р°Рµ СѓСЃРїРµС…Р°, РёР»Рё false РІ СЃР»СѓС‡Р°Рµ РѕС€РёР±РєРё
     */
    public function updatePageData($pageData = [], $language_code = ENV_DEF_LANG): OperationResult {
        try {
            $urlPolicyId = (int) ($pageData['url_policy_id'] ?? 0);
            $hasSearchControls = array_key_exists('search_enabled', $pageData)
                || array_key_exists('search_scope_mask', $pageData)
                || array_key_exists('search_scope_public', $pageData)
                || array_key_exists('search_scope_manager', $pageData)
                || array_key_exists('search_scope_admin', $pageData);
            $normalizedSearchEnabled = null;
            $normalizedSearchScopeMask = null;
            if ($hasSearchControls) {
                $normalizedSearchEnabled = !empty($pageData['search_enabled'])
                    && !in_array((string) $pageData['search_enabled'], ['0', 'false', 'off'], true)
                    ? 1
                    : 0;
                if (array_key_exists('search_scope_mask', $pageData) && is_numeric($pageData['search_scope_mask'])) {
                    $normalizedSearchScopeMask = max(0, min(Constants::SEARCH_SCOPE_ALL, (int) $pageData['search_scope_mask']));
                } else {
                    $normalizedSearchScopeMask = 0;
                    if (!empty($pageData['search_scope_public'])) {
                        $normalizedSearchScopeMask |= Constants::SEARCH_SCOPE_PUBLIC;
                    }
                    if (!empty($pageData['search_scope_manager'])) {
                        $normalizedSearchScopeMask |= Constants::SEARCH_SCOPE_MANAGER;
                    }
                    if (!empty($pageData['search_scope_admin'])) {
                        $normalizedSearchScopeMask |= Constants::SEARCH_SCOPE_ADMIN;
                    }
                }
            }
            $pageData = SafeMySQL::gi()->filterArray($pageData, SysClass::ee_getFieldsTable(Constants::PAGES_TABLE));
            if ($hasSearchControls) {
                $pageData['search_enabled'] = $normalizedSearchEnabled;
                $pageData['search_scope_mask'] = $normalizedSearchScopeMask;
            }
            $pageData = array_map('trim', $pageData);
            $pageData = SysClass::ee_convertArrayValuesToNumbers($pageData);
            if (empty($pageData['category_id']) || empty($pageData['title'])) {
                Logger::warning('page_validation', 'Отсутствует category_id или title', ['pageData' => $pageData], ['initiator' => __FUNCTION__]);
                return OperationResult::validation('Отсутствует category_id или title', ['pageData' => $pageData]);
            }
            $pageData['parent_page_id'] = isset($pageData['parent_page_id']) && $pageData['parent_page_id'] !== 0 ? $pageData['parent_page_id'] : NULL;
            $requestedLanguageCode = strtoupper(trim((string) ($pageData['language_code'] ?? $language_code)));
            $resolvedLanguageCode = $requestedLanguageCode;
            if (!empty($pageData['parent_page_id'])) {
                $parentPageData = SafeMySQL::gi()->getRow(
                    'SELECT category_id, language_code FROM ?n WHERE page_id = ?i LIMIT 1',
                    Constants::PAGES_TABLE,
                    (int) $pageData['parent_page_id']
                ) ?: null;
                if (!$parentPageData || empty($parentPageData['category_id'])) {
                    Logger::warning('page_validation', 'Родительская страница не найдена', ['pageData' => $pageData], ['initiator' => __FUNCTION__]);
                    return OperationResult::validation('Родительская страница не найдена', ['pageData' => $pageData]);
                }
                $pageData['category_id'] = (int) $parentPageData['category_id'];
                $resolvedLanguageCode = strtoupper(trim((string) ($parentPageData['language_code'] ?? $resolvedLanguageCode)));
            } else {
                $pageData['category_id'] = (int) $pageData['category_id'];
            }
            $categoryLanguageCode = SafeMySQL::gi()->getOne(
                'SELECT language_code FROM ?n WHERE category_id = ?i LIMIT 1',
                Constants::CATEGORIES_TABLE,
                (int) $pageData['category_id']
            );
            if ($resolvedLanguageCode === '' && !empty($categoryLanguageCode)) {
                $resolvedLanguageCode = strtoupper(trim((string) $categoryLanguageCode));
            }
            if ($resolvedLanguageCode === '') {
                $resolvedLanguageCode = strtoupper((string) ENV_DEF_LANG);
            }
            $pageData['language_code'] = $resolvedLanguageCode;
            if (empty($pageData['title'])) {
                Logger::warning('page_validation', 'Отсутствует title', ['pageData' => $pageData], ['initiator' => __FUNCTION__]);
                return OperationResult::validation('Отсутствует title', ['pageData' => $pageData]);
            }

            $oldCategoryId = null;
            $oldPageRow = null;
            if (!empty($pageData['page_id'])) {
                if (!empty($pageData['parent_page_id']) && $this->isAncestorPage($pageData['parent_page_id'], $pageData['page_id'])) {
                    Logger::warning('page_validation', 'Страница не может быть родителем для самой себя или своих предков', ['pageData' => $pageData], ['initiator' => __FUNCTION__]);
                    return OperationResult::validation('Страница не может быть родителем для самой себя или своих предков', ['pageData' => $pageData]);
                }
                $pageId = $pageData['page_id'];
                $oldPageRow = SafeMySQL::gi()->getRow(
                    'SELECT category_id, parent_page_id, language_code, slug, route_path FROM ?n WHERE page_id = ?i LIMIT 1',
                    Constants::PAGES_TABLE,
                    $pageId
                ) ?: null;
                $oldCategoryId = (int) ($oldPageRow['category_id'] ?? 0);
                $method = 'update';
            } else {
                $method = 'insert';
                $pageId = 0;
            }

            if (is_array($oldPageRow) && empty($pageData['slug'])) {
                $pageData['slug'] = (string) ($oldPageRow['slug'] ?? '');
                $pageData['preserve_existing_slug'] = (string) ($pageData['slug'] ?? '') !== '';
            }
            $pageData['url_policy_id'] = $urlPolicyId;
            $pageData['slug'] = EntitySlugService::generatePageSlug($pageData, $pageId > 0 ? $pageId : null);
            if (is_array($oldPageRow) && !array_key_exists('route_path', $pageData)) {
                $pageData['route_path'] = (string) ($oldPageRow['route_path'] ?? '');
            }
            $pageData['route_path'] = EntitySlugService::generatePageRoutePath($pageData, $pageId > 0 ? $pageId : null);
            if ((string) ($pageData['route_path'] ?? '') === '') {
                $pageData['route_path'] = null;
            }
            unset($pageData['preserve_existing_slug'], $pageData['url_policy_id']);

            if ($method === 'update') {
                unset($pageData['page_id']);
                $sql = "UPDATE ?n SET ?u WHERE page_id = ?i";
                $result = SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, $pageData, $pageId);
            } else {
                unset($pageData['page_id']);
                $sql = "INSERT INTO ?n SET ?u";
                $result = SafeMySQL::gi()->query($sql, Constants::PAGES_TABLE, $pageData);
                $pageId = SafeMySQL::gi()->insertId();
            }

            if ($method == 'insert') {
                $objectModelProperties = SysClass::getModelObject('admin', 'm_properties');
                $objectModelProperties->createPropertiesValueEntities('page', $pageId, $pageData);
            }

            if ($method === 'update' && $oldCategoryId !== null && $oldCategoryId !== (int) $pageData['category_id']) {
                $pageData['old_category_id'] = $oldCategoryId;
            }
            if ($method === 'update' && is_array($oldPageRow)) {
                $pageData['old_parent_page_id'] = $oldPageRow['parent_page_id'] ?? null;
                $pageData['language_code'] = $pageData['language_code'] ?? ($oldPageRow['language_code'] ?? $language_code);
            }

            if (!$result || (int) $pageId <= 0) {
                Logger::error('page_sql_error', 'Не удалось сохранить страницу', ['pageData' => $pageData, 'query' => SafeMySQL::gi()->lastQuery()], ['initiator' => __FUNCTION__]);
                return OperationResult::failure('Не удалось сохранить страницу', 'page_sql_error', ['pageData' => $pageData]);
            }

            EntityTranslationService::ensureEntity('page', (int) $pageId);
            Hook::run('afterUpdatePageData', $pageId, $pageData, $method);
            return OperationResult::success((int) $pageId, '', $method === 'insert' ? 'created' : 'updated');
        } catch (\Throwable $e) {
            Logger::error('page_error', 'Исключение при сохранении страницы: ' . $e->getMessage(), ['pageData' => $pageData ?? [], 'exception' => $e], ['initiator' => __FUNCTION__, 'include_trace' => true]);
            return OperationResult::failure('Исключение при сохранении страницы: ' . $e->getMessage(), 'page_exception', ['pageData' => $pageData ?? []]);
        }
    }

    /**
     * РџСЂРѕРІРµСЂСЏРµС‚, РјРѕР¶РЅРѕ Р»Рё РЅР°Р·РЅР°С‡РёС‚СЊ СЂРѕРґРёС‚РµР»СЏ ancestorPageId РґР»СЏ СЃС‚СЂР°РЅРёС†С‹ pageId
     * @param int $ancestorPageId РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ РїСЂРµРґРїРѕР»Р°РіР°РµРјРѕРіРѕ СЂРѕРґРёС‚РµР»СЏ
     * @param int $pageId РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃС‚СЂР°РЅРёС†С‹, РґР»СЏ РєРѕС‚РѕСЂРѕР№ РЅР°Р·РЅР°С‡Р°РµС‚СЃСЏ СЂРѕРґРёС‚РµР»СЊ
     * @return bool Р’РѕР·РІСЂР°С‰Р°РµС‚ true, РµСЃР»Рё РЅР°Р·РЅР°С‡РµРЅРёРµ РЅРµРІРѕР·РјРѕР¶РЅРѕ (РµСЃС‚СЊ С†РёРєР»РёС‡РµСЃРєР°СЏ Р·Р°РІРёСЃРёРјРѕСЃС‚СЊ), РёРЅР°С‡Рµ false
     */
    private function isAncestorPage($ancestorPageId, $pageId) {
        if ($ancestorPageId === null || $pageId === null) {
            return false;
        }
        $currentParentId = $ancestorPageId;
        while ($currentParentId !== null) {
            if ($currentParentId == $pageId) {
                return true;
            }
            $currentParentId = SafeMySQL::gi()->getOne('SELECT parent_page_id FROM ?n WHERE page_id = ?i', Constants::PAGES_TABLE, $currentParentId);
        }
        $descendants = $this->getDescendants($ancestorPageId);
        if (in_array($pageId, $descendants, true)) {
            return true;
        }
        return false;
    }

    /**
     * Р’РѕР·РІСЂР°С‰Р°РµС‚ РјР°СЃСЃРёРІ РІСЃРµС… РїРѕС‚РѕРјРєРѕРІ РґР»СЏ Р·Р°РґР°РЅРЅРѕР№ СЃС‚СЂР°РЅРёС†С‹
     * @param int $pageId РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃС‚СЂР°РЅРёС†С‹, РґР»СЏ РєРѕС‚РѕСЂРѕР№ РёС‰РµРј РїРѕС‚РѕРјРєРѕРІ
     * @return array РњР°СЃСЃРёРІ РёРґРµРЅС‚РёС„РёРєР°С‚РѕСЂРѕРІ РїРѕС‚РѕРјРєРѕРІ
     */
    private function getDescendants($pageId) {
        $descendants = [];
        $queue = [$pageId];
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $children = SafeMySQL::gi()->getCol('SELECT page_id FROM ?n WHERE parent_page_id = ?i', Constants::PAGES_TABLE, $currentId);
            foreach ($children as $childId) {
                if (!in_array($childId, $descendants, true)) {
                    $descendants[] = $childId;
                    $queue[] = $childId;
                }
            }
        }
        return $descendants;
    }

    /**
     * РЈРґР°Р»СЏРµС‚ СЃС‚СЂР°РЅРёС†Сѓ РїРѕ СѓРєР°Р·Р°РЅРЅРѕРјСѓ page_id РёР· С‚Р°Р±Р»РёС†С‹ pages Рё СЃРІСЏР·Р°РЅРЅС‹Рµ Р·РЅР°С‡РµРЅРёСЏ РёР· property_values
     * @param int $pageId РРґРµРЅС‚РёС„РёРєР°С‚РѕСЂ СЃС‚СЂР°РЅРёС†С‹ РґР»СЏ СѓРґР°Р»РµРЅРёСЏ
     * @return array Р’РѕР·РІСЂР°С‰Р°РµС‚ РїСѓСЃС‚РѕР№ РјР°СЃСЃРёРІ РІ СЃР»СѓС‡Р°Рµ СѓСЃРїРµС€РЅРѕРіРѕ СѓРґР°Р»РµРЅРёСЏ РёР»Рё РјР°СЃСЃРёРІ СЃ РёРЅС„РѕСЂРјР°С†РёРµР№ РѕР± РѕС€РёР±РєРµ
     */
    public function deletePage($pageId): OperationResult {
        try {
            $pageData = SafeMySQL::gi()->getRow(
                'SELECT page_id, category_id, parent_page_id, language_code FROM ?n WHERE page_id = ?i LIMIT 1',
                Constants::PAGES_TABLE,
                $pageId
            ) ?: null;
            $sql_check = "SELECT COUNT(*) FROM ?n WHERE parent_page_id = ?i";
            $count = SafeMySQL::gi()->getOne($sql_check, Constants::PAGES_TABLE, $pageId);
            if ($count > 0) {
                Logger::warning('delete_page', 'Нельзя удалить страницу, так как она является родительской для других.', ['page_id' => $pageId], ['initiator' => __FUNCTION__]);
                return OperationResult::failure('Нельзя удалить страницу, так как она является родительской для других.', 'delete_page_blocked', ['page_id' => $pageId]);
            }
            $sql_delete_page = "DELETE FROM ?n WHERE page_id = ?i";
            $result = SafeMySQL::gi()->query($sql_delete_page, Constants::PAGES_TABLE, $pageId);
            if ($result) {
                EntityTranslationService::removeEntityTranslation('page', (int) $pageId);
                $sql_delete_properties = "DELETE FROM ?n WHERE entity_id = ?i AND entity_type = 'page'";
                SafeMySQL::gi()->query($sql_delete_properties, Constants::PROPERTY_VALUES_TABLE, $pageId);
                Hook::run('afterDeletePage', $pageId, $pageData, 'delete');
                return OperationResult::success(['page_id' => (int) $pageId], '', 'deleted');
            }
            Logger::error('delete_page', 'Ошибка при выполнении запроса DELETE для ' . $pageId, ['page_id' => $pageId, 'query' => SafeMySQL::gi()->lastQuery()], ['initiator' => __FUNCTION__]);
            return OperationResult::failure('Ошибка при выполнении запроса DELETE для ' . $pageId, 'delete_page_sql_error', ['page_id' => $pageId]);
        } catch (Exception $e) {
            Logger::error('delete_page', $e->getMessage(), ['page_id' => $pageId, 'exception' => $e], ['initiator' => __FUNCTION__, 'include_trace' => true]);
            return OperationResult::failure($e->getMessage(), 'delete_page_exception', ['page_id' => $pageId]);
        }
    }

    /**
     * Быстро обновляет участие страницы в поиске, не открывая полную форму редактирования.
     */
    public function updatePageSearchState(int $pageId, bool $searchEnabled): OperationResult {
        if ($pageId <= 0) {
            return OperationResult::validation('Неверный ID страницы', ['page_id' => $pageId]);
        }

        try {
            $pageRow = SafeMySQL::gi()->getRow(
                'SELECT page_id, language_code, search_scope_mask FROM ?n WHERE page_id = ?i LIMIT 1',
                Constants::PAGES_TABLE,
                $pageId
            );
            if (!$pageRow) {
                return OperationResult::failure('Страница не найдена', 'page_not_found', ['page_id' => $pageId]);
            }

            $updateData = [
                'search_enabled' => $searchEnabled ? 1 : 0,
            ];
            if ($searchEnabled && (int) ($pageRow['search_scope_mask'] ?? 0) === 0) {
                $updateData['search_scope_mask'] = Constants::SEARCH_SCOPE_ALL;
            }

            $result = SafeMySQL::gi()->query(
                'UPDATE ?n SET ?u WHERE page_id = ?i',
                Constants::PAGES_TABLE,
                $updateData,
                $pageId
            );
            if ($result === false) {
                return OperationResult::failure('Не удалось обновить участие страницы в поиске', 'page_search_update_error', [
                    'page_id' => $pageId,
                    'query' => SafeMySQL::gi()->lastQuery(),
                ]);
            }

            scheduleSearchEntityReindex('page', $pageId, (string) ($pageRow['language_code'] ?? ENV_DEF_LANG));
            schedulePublicHtmlCacheClear(__FUNCTION__, [
                'page_id' => $pageId,
                'search_enabled' => $searchEnabled ? 1 : 0,
            ]);

            return OperationResult::success(['page_id' => $pageId], '', 'updated');
        } catch (\Throwable $e) {
            Logger::error('page_search_toggle', 'Ошибка при обновлении участия страницы в поиске: ' . $e->getMessage(), [
                'page_id' => $pageId,
                'search_enabled' => $searchEnabled ? 1 : 0,
                'exception' => $e,
            ], ['initiator' => __FUNCTION__, 'include_trace' => true]);

            return OperationResult::failure(
                'Ошибка при обновлении участия страницы в поиске: ' . $e->getMessage(),
                'page_search_toggle_error',
                ['page_id' => $pageId]
            );
        }
    }
}
