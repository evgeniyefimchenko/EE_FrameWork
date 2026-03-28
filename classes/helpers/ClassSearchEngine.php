<?php

namespace classes\helpers;

use \classes\plugins\SafeMySQL;
use \classes\system\SysClass;
use \classes\system\Constants;
use \classes\system\EntityPublicUrlService;
use \classes\system\Logger;
use \classes\system\PropertyFieldContract;

/**
 * Класс для управления поисковым индексом и выполнения поиска по сайту
 */
class ClassSearchEngine {

    private $db;

    private const MIN_WORD_LENGTH = 3; // Минимальная длина слова для поиска/индексации
    private const NGRAM_LENGTH = 3;    // Длина N-граммы
    private const MAX_NGRAM_SOURCE_LENGTH = 768; // Ограничение текста для fuzzy-индекса
    private const REBUILD_BATCH_SIZE = 200;
    // Порог схожести N-грамм для нечеткого поиска (0.0 до 1.0)
    private const NGRAM_SIMILARITY_THRESHOLD = 0.3;
    // Лимит результатов для нечеткого поиска (может отличаться от основного)
    private const FUZZY_SEARCH_LIMIT = 10;
    // Порог количества результатов FULLTEXT, при котором запускается нечеткий поиск
    private const FUZZY_FALLBACK_THRESHOLD = 5;
    // Базовые ранги для сущностей (лучше вынести в Constants)
    private const STATIC_RANK_MAP = [
        'category' => 50,
        'page' => 70,
        'user' => 30,
        'property' => 20,
            // Добавьте другие типы
    ];
    // Суффиксы URL для админки (лучше вынести в Constants)
    private const ADMIN_URL_SUFFIX_MAP = [
        'category' => 'category_edit/id/',
        'page' => 'page_edit/id/',
        'property' => 'edit_property/id/',
        'type' => 'type_properties_edit/id/', // Уточните entity_type при индексации
        'set' => 'edit_property_set/id/', // Уточните entity_type при индексации
        'email_template' => 'edit_email_template/id/',
        'email_snippet' => 'email_snippet_edit/id/',
            // Добавьте другие типы
    ];
    private const SEARCHABLE_FIELD_TYPES = [
        'text',
        'number',
        'date',
        'time',
        'datetime-local',
        'email',
        'phone',
        'textarea',
        'select',
        'checkbox',
        'radio',
    ];

    public function __construct() {
        $this->db = SafeMySQL::gi();
    }

    // === МЕТОДЫ ДЛЯ УПРАВЛЕНИЯ ИНДЕКСОМ ===

    /**
     * Обновляет или создает запись в поисковом индексе (и N-граммы)
     * @param array $data Ассоциативный массив с подготовленными данными
     * Ключи: entity_id(int), entity_type(string), language_code(string, опц), title(string), content_full(string), url(string, опц)
     * @return bool Статус успеха
     */
    public function updateIndexEntry(array $data): bool {
        if (empty($data['entity_id']) || empty($data['entity_type']) || !isset($data['title'], $data['content_full'])) {
            return false;
        }

        if (empty(trim($data['title'])) && empty(trim($data['content_full']))) {
            return $this->removeIndexEntry((string)$data['entity_type'], (int)$data['entity_id']);
        }
        
        try {
            $entityType = strtolower($data['entity_type']);
            $staticRank = self::STATIC_RANK_MAP[$entityType] ?? 50;
            $languageCode = $data['language_code'] ?? ENV_DEF_LANG;
            
            $indexData = [
                'entity_id'     => (int) $data['entity_id'],
                'entity_type'   => $entityType,
                'language_code' => $languageCode,
                'title'         => self::prepareTitle($data['title']),
                'content_full'  => self::prepareContent($data['content_full']),
                'url'           => $data['url'] ?? '',
                'static_rank'   => $staticRank
            ];

            $sql = "INSERT INTO ?n SET ?u ON DUPLICATE KEY UPDATE 
                    title = VALUES(title), 
                    content_full = VALUES(content_full), 
                    url = VALUES(url), 
                    static_rank = VALUES(static_rank), 
                    last_updated = NOW()";
            $this->db->query($sql, Constants::SEARCH_INDEX_TABLE, $indexData);

            $searchId = $this->db->getOne(
                "SELECT search_id FROM ?n WHERE entity_type = ?s AND entity_id = ?i AND language_code = ?s",
                Constants::SEARCH_INDEX_TABLE, $entityType, (int)$data['entity_id'], $languageCode
            );
            
            if ($searchId) {
                $textForNgrams = self::prepareNgramSource($indexData['title'], $indexData['content_full']);
                $ngrams = self::generateNgrams($textForNgrams);
                $this->updateNgramsInDb((int)$searchId, $ngrams);
            } else {
                 return false;
            }

            return true;
        } catch (\Throwable $e) {
            Logger::error('search_index_error', 'Критическая ошибка updateIndexEntry: ' . $e->getMessage(), ['data' => $data], [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Выполняет "нечеткий" поиск по N-граммам для поиска с опечатками.
     * @param string $query Поисковый запрос
     * @param bool $isAdminSearch Флаг поиска в админ-панели
     * @param string $lang Код языка
     * @param int $limit Лимит результатов
     * @return array Массив с найденными результатами
     */
    private function executeNgramSearch(string $query, bool $isAdminSearch, string $lang, int $limit): array {
        // Разбиваем поисковый запрос на "кусочки"-триграммы
        $ngrams = self::generateNgrams(self::prepareContent($query));
        if (empty($ngrams)) {
            return [];
        }

        // Фильтр по типу сущности для публичной части сайта
        $entityTypeFilter = (!$isAdminSearch) ? "AND si.entity_type IN ('page', 'category')" : '';

        // Находим документы, содержащие наши триграммы, и ранжируем их по количеству совпадений
        $sql = "SELECT si.search_id, si.entity_id, si.entity_type, si.title, si.popularity_score, si.static_rank, (t.ngram_matches / ?i) AS relevance
                FROM ?n si
                JOIN (
                    SELECT search_id, COUNT(ngram_id) AS ngram_matches
                    FROM ?n
                    WHERE ngram IN (?a)
                    GROUP BY search_id
                ) t ON si.search_id = t.search_id
                WHERE si.language_code = ?s ?p
                ORDER BY relevance DESC, si.popularity_score DESC, si.static_rank DESC
                LIMIT ?i";

        return $this->db->getAll(
            $sql,
            count($ngrams),
            Constants::SEARCH_INDEX_TABLE,
            Constants::SEARCH_NGRAMS_TABLE,
            $ngrams,
            $lang,
            $entityTypeFilter,
            $limit
        );
    }    
    
    /**
     * Удаляет сущность из поискового индекса (включая N-граммы)
     * @param string $entityType Тип сущности
     * @param int $entityId ID сущности
     * @return bool Статус успеха
     */
    public function removeIndexEntry(string $entityType, int $entityId): bool {
        if ($entityId <= 0)
            return false;
        $entityType = strtolower($entityType);
        try {
            $searchIds = $this->db->getCol(
                    "SELECT search_id FROM ?n WHERE entity_type = ?s AND entity_id = ?i",
                    Constants::SEARCH_INDEX_TABLE, $entityType, $entityId
            );
            if (!empty($searchIds))
                $this->removeNgramsFromDb($searchIds);

            $sqlDelete = "DELETE FROM ?n WHERE entity_type = ?s AND entity_id = ?i";
            $result = $this->db->query($sqlDelete, Constants::SEARCH_INDEX_TABLE, $entityType, $entityId);
            return (bool) $result;
        } catch (\Throwable $e) {
            Logger::error('search_index_error', "Ошибка removeIndexEntry {$entityType} ID {$entityId}: " . $e->getMessage(), ['entity_type' => $entityType, 'entity_id' => $entityId], [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
            return false;
        }
    }

    // === ВСПОМОГАТЕЛЬНЫЕ СТАТИЧЕСКИЕ МЕТОДЫ ДЛЯ ПОДГОТОВКИ ТЕКСТА ===

    /**
     * Очищает текст для поля content_full
     * @param string $text
     * @return string
     */
    public static function prepareContent(string $text): string {
        // 1. Стандартная очистка от тегов и спецсимволов
        $text = strip_tags($text ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s\']/u', ' ', $text);
        $text = trim(preg_replace('/\s+/', ' ', $text));

        // 2. Удаление дубликатов слов
        if (empty($text)) {
            return '';
        }
        $words = explode(' ', $text);
        $uniqueWords = array_unique($words);
        
        return implode(' ', $uniqueWords);
    }

    /**
     * Очищает текст для поля title
     * @param string $text
     * @return string
     */
    public static function prepareTitle(string $text): string {
        $text = strip_tags($text ?? '');
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        return mb_substr($text, 0, 255);
    }

    /**
     * Генерирует уникальные N-граммы (триграммы) для текста
     * @param string $text Очищенный текст
     * @return array Массив уникальных N-грамм
     */
    public static function generateNgrams(string $text): array {
        $ngrams = [];
        $len = mb_strlen($text);
        if ($len >= self::NGRAM_LENGTH) {
            for ($i = 0; $i <= $len - self::NGRAM_LENGTH; $i++) {
                $ngram = mb_substr($text, $i, self::NGRAM_LENGTH);
                if (preg_match('/[\p{L}\p{N}]/u', $ngram)) {
                    $ngrams[$ngram] = true;
                }
            }
        }
        return array_keys($ngrams);
    }

    // === МЕТОДЫ ДЛЯ ПОИСКА ===

    /**
     * Основной метод поиска
     * @param string $query Поисковый запрос
     * @param string $lang Код языка
     * @param int $limit Лимит результатов
     * @param int $offset Смещение (для пагинации)
     * @return array Массив с результатами и общим количеством ['results' => [], 'total' => 0]
     */
    public function search(string $query, string $lang = ENV_DEF_LANG, int $limit = 20, int $offset = 0): array {
        $originalQuery = trim($query);
        if (empty($originalQuery)) {
            return ['results' => [], 'total' => 0];
        }

        $isAdminSearch = (defined('ENV_CONTROLLER_FOLDER') && ENV_CONTROLLER_FOLDER === 'admin');
        $areaCode = $isAdminSearch ? 'A' : 'C';

        $searchQueryPrepared = self::prepareSearchQueryBoolean($originalQuery);
        $normalizedQuery = $this->normalizeQuery($originalQuery);
        $this->logSearchQuery($originalQuery, $areaCode, $lang, $normalizedQuery);

        if (empty($searchQueryPrepared)) {
            return ['results' => [], 'total' => 0];
        }
        
        $results = [];
        $total = 0;

        try {
            // Этап 1: Поиск с FULLTEXT
            list($fulltextResults, $total) = $this->executeFullTextSearch($searchQueryPrepared, $isAdminSearch, $lang, $limit, $offset);
            $results = $fulltextResults;

            // Этап 2: Если результатов мало, пробуем нечеткий поиск (N-граммы)
            if ($total < self::FUZZY_FALLBACK_THRESHOLD || count($results) < self::FUZZY_FALLBACK_THRESHOLD) {
                $fuzzyResults = $this->executeNgramSearch($originalQuery, $isAdminSearch, $lang, self::FUZZY_SEARCH_LIMIT);
                $results = $this->mergeResults($results, $fuzzyResults, $limit);
                $total = count($results);
            }
        } catch (\Throwable $e) {
            Logger::error('search_error', 'Ошибка SearchEngine::search: ' . $e->getMessage(), ['query' => $originalQuery, 'lang' => $lang], [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
            return ['results' => [], 'total' => 0, 'error' => 'Search error occurred'];
        }

        // Этап 3: Форматирование результатов
        $formattedResults = [];
        if (!empty($results)) {
            foreach ($results as $row) {
                $formattedRow = [
                    'id' => (int) $row['entity_id'],
                    'type' => $row['entity_type'],
                    'title' => $row['title'],
                    'relevance' => round((float) ($row['relevance'] ?? 0), 2),
                    'url' => $isAdminSearch
                        ? $this->getAdminEditUrl($row['entity_type'], $row['entity_id'])
                        : ((string) ($row['url'] ?? '') !== ''
                            ? (string) $row['url']
                            : EntityPublicUrlService::buildEntityUrl((string) $row['entity_type'], (int) $row['entity_id'], (string) ($row['language_code'] ?? $lang))),
                ];
                $formattedResults[] = $formattedRow;
            }
        }
        return ['results' => $formattedResults, 'total' => $total];
    }

    /**
     * Автодополнение
     * @param string $term Частичный ввод
     * @param string $lang Код языка
     * @param int $limit Лимит
     * @return array Массив подсказок
     */
    public function autocomplete(string $term, string $lang = ENV_DEF_LANG, int $limit = 10): array {
        $term = trim($term);
        $normTerm = $this->normalizeQuery($term);
        if (mb_strlen($normTerm) < 2)
            return [];

        $isAdminSearch = (defined('ENV_CONTROLLER_FOLDER') && ENV_CONTROLLER_FOLDER === 'admin');
        $areaCode = $isAdminSearch ? 'A' : 'C';

        $suggestions = [];
        $processedValues = [];

        try {
            // 1. Популярные запросы из лога
            $popularQueries = $this->db->getAll(
                    "SELECT query_text FROM ?n
                  WHERE normalized_query LIKE ?s AND language_code = ?s -- Убрали area
                  ORDER BY hit_count DESC LIMIT ?i",
                    Constants::SEARCH_LOG_TABLE, $normTerm . '%', $lang, $limit
            );
            foreach ($popularQueries as $pq) {
                $value = $pq['query_text'];
                if (!isset($processedValues[$value])) {
                    $suggestions[] = ['value' => $value, 'label' => $value, 'type' => 'query'];
                    $processedValues[$value] = true;
                }
            }

            // 2. Заголовки из индекса
            $remainingLimit = $limit - count($suggestions);
            if ($remainingLimit > 0) {
                $titlePrefixQuery = $term . '%';
                $entityTypeFilter = (!$isAdminSearch) ? $this->db->parse("AND entity_type IN ('page', 'category')") : '';
                $titleMatches = $this->db->getAll(
                        "SELECT entity_id, entity_type, title, language_code, url FROM ?n
                       WHERE title LIKE ?s AND language_code = ?s ?p
                       ORDER BY popularity_score DESC, static_rank DESC LIMIT ?i",
                        Constants::SEARCH_INDEX_TABLE, $titlePrefixQuery, $lang, $entityTypeFilter, $remainingLimit
                );
                foreach ($titleMatches as $tm) {
                    $value = $tm['title'];
                    if (!isset($processedValues[$value])) {
                        $label = $value;
                        $url = null;
                        if ($isAdminSearch) {
                            $url = $this->getAdminEditUrl($tm['entity_type'], $tm['entity_id']);
                            $label .= " ({$tm['entity_type']})"; // Добавляем тип в label админки
                        } else {
                            $url = (string) ($tm['url'] ?? '');
                            if ($url === '') {
                                $url = EntityPublicUrlService::buildEntityUrl((string) $tm['entity_type'], (int) $tm['entity_id'], (string) ($tm['language_code'] ?? $lang));
                            }
                        }
                        $suggestions[] = ['value' => $value, 'label' => $label, 'type' => $tm['entity_type'], 'url' => $url];
                        $processedValues[$value] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            Logger::error('search_error', 'Ошибка SearchEngine::autocomplete: ' . $e->getMessage(), ['term' => $term, 'lang' => $lang], [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
            return [];
        }
        return $suggestions;
    }

    /**
     * Переиндексирует одну сущность по её текущему состоянию в БД.
     */
    public function reindexEntity(string $entityType, int $entityId, ?string $languageCode = null): bool {
        $entityType = strtolower(trim($entityType));
        if ($entityId <= 0 || !in_array($entityType, ['page', 'category'], true)) {
            return false;
        }

        $entityRow = $this->loadEntityRow($entityType, $entityId, $languageCode);
        if ($entityRow === null) {
            return $this->removeIndexEntry($entityType, $entityId);
        }

        if (($entityRow['status'] ?? '') !== 'active') {
            return $this->removeIndexEntry($entityType, $entityId);
        }

        $indexData = $this->buildIndexPayloadFromEntityRow($entityType, $entityRow);
        if ($indexData === null) {
            return $this->removeIndexEntry($entityType, $entityId);
        }

        return $this->updateIndexEntry($indexData);
    }

    /**
     * Возвращает текст свойств одной сущности, пригодный для добавления в search_index.
     */
    public function getEntityPropertySearchText(string $entityType, int $entityId, string $languageCode = ENV_DEF_LANG): string {
        $map = $this->collectEntityPropertySearchTextMap($entityType, [$entityId], $languageCode);
        return $map[$entityId] ?? '';
    }

    /**
     * Батчево собирает поисковый текст из property_values, чтобы не делать N+1 на rebuild.
     *
     * @return array<int, string> [entity_id => prepared_text]
     */
    public function collectEntityPropertySearchTextMap(string $entityType, array $entityIds, string $languageCode = ENV_DEF_LANG): array {
        $entityType = strtolower(trim($entityType));
        $entityIds = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn(int $id): bool => $id > 0)));
        if (empty($entityIds) || !in_array($entityType, ['page', 'category'], true)) {
            return [];
        }

        $rows = $this->db->getAll(
            'SELECT pv.entity_id, pv.property_values, p.name, p.default_values, p.is_multiple, p.is_required, pt.fields AS type_fields
             FROM ?n AS pv
             INNER JOIN ?n AS p ON p.property_id = pv.property_id
             LEFT JOIN ?n AS pt ON pt.type_id = p.type_id
             WHERE pv.entity_type = ?s
               AND pv.entity_id IN (?a)
               AND pv.language_code = ?s
               AND p.language_code = ?s
               AND p.status = ?s
             ORDER BY pv.entity_id ASC, pv.property_id ASC',
            Constants::PROPERTY_VALUES_TABLE,
            Constants::PROPERTIES_TABLE,
            Constants::PROPERTY_TYPES_TABLE,
            $entityType,
            $entityIds,
            $languageCode,
            $languageCode,
            'active'
        );

        $preparedByEntity = array_fill_keys($entityIds, '');
        foreach ($rows as $row) {
            $entityId = (int) ($row['entity_id'] ?? 0);
            if ($entityId <= 0) {
                continue;
            }
            $runtimeFields = PropertyFieldContract::buildRuntimeFields(
                $row['default_values'] ?? [],
                $row['property_values'] ?? [],
                $row['type_fields'] ?? [],
                $row
            );
            $fieldText = $this->buildSearchTextFromRuntimeFields($runtimeFields);
            if ($fieldText === '') {
                continue;
            }
            $preparedByEntity[$entityId] = trim($preparedByEntity[$entityId] . ' ' . $fieldText);
        }

        foreach ($preparedByEntity as $entityId => $text) {
            $preparedByEntity[$entityId] = self::prepareContent($text);
        }

        return $preparedByEntity;
    }

    /**
     * Диагностика поисковой схемы и обязательных индексов.
     * Возвращает структуру, пригодную для smoke/regression-проверок.
     */
    public function getSchemaDiagnostics(): array {
        $requiredIndexes = [
            Constants::SEARCH_INDEX_TABLE => [
                'uq_entity' => ['type' => 'BTREE', 'columns' => 'entity_id,entity_type,language_code'],
                'idx_content' => ['type' => 'FULLTEXT', 'columns' => 'title,content_full'],
                'idx_entity' => ['type' => 'BTREE', 'columns' => 'entity_type,entity_id'],
                'idx_lookup' => ['type' => 'BTREE', 'columns' => 'language_code'],
                'idx_rank' => ['type' => 'BTREE', 'columns' => 'language_code,popularity_score,static_rank'],
                'idx_title_prefix' => ['type' => 'BTREE', 'columns' => 'title'],
            ],
            Constants::SEARCH_NGRAMS_TABLE => [
                'idx_ngram_search' => ['type' => 'BTREE', 'columns' => 'ngram,search_id'],
                'idx_search_id' => ['type' => 'BTREE', 'columns' => 'search_id'],
            ],
            Constants::SEARCH_LOG_TABLE => [
                'uq_query' => ['type' => 'BTREE', 'columns' => 'normalized_query,area,language_code'],
                'idx_hits' => ['type' => 'BTREE', 'columns' => 'hit_count'],
            ],
        ];

        $tableNames = array_keys($requiredIndexes);
        $existingTables = $this->db->getCol(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?a)',
            $tableNames
        );
        $tablePresence = array_fill_keys($tableNames, false);
        foreach ($existingTables as $tableName) {
            $tablePresence[$tableName] = true;
        }

        $indexRows = $this->db->getAll(
            'SELECT TABLE_NAME, INDEX_NAME, INDEX_TYPE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR \',\') AS columns_list
             FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (?a)
             GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE',
            $tableNames
        );
        $existingIndexes = [];
        foreach ($indexRows as $row) {
            $existingIndexes[$row['TABLE_NAME']][$row['INDEX_NAME']] = [
                'type' => strtoupper((string) ($row['INDEX_TYPE'] ?? 'BTREE')),
                'columns' => (string) ($row['columns_list'] ?? ''),
            ];
        }

        $issues = [];
        $indexStatus = [];
        foreach ($requiredIndexes as $tableName => $indexes) {
            $indexStatus[$tableName] = [];
            foreach ($indexes as $indexName => $expected) {
                $actual = $existingIndexes[$tableName][$indexName] ?? null;
                $present = $actual !== null;
                $typeMatches = $present && strtoupper($actual['type']) === strtoupper($expected['type']);
                $columnMatches = $present && $actual['columns'] === $expected['columns'];
                $indexStatus[$tableName][$indexName] = [
                    'present' => $present,
                    'expected' => $expected,
                    'actual' => $actual,
                    'matches' => $present && $typeMatches && $columnMatches,
                ];
                if (!$present) {
                    $issues[] = "Missing index {$tableName}.{$indexName}";
                } elseif (!$typeMatches || !$columnMatches) {
                    $issues[] = "Index mismatch {$tableName}.{$indexName}";
                }
            }
        }

        $tableCounts = [
            Constants::SEARCH_INDEX_TABLE => $tablePresence[Constants::SEARCH_INDEX_TABLE]
                ? (int) $this->db->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_INDEX_TABLE)
                : null,
            Constants::SEARCH_NGRAMS_TABLE => $tablePresence[Constants::SEARCH_NGRAMS_TABLE]
                ? (int) $this->db->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_NGRAMS_TABLE)
                : null,
            Constants::SEARCH_LOG_TABLE => $tablePresence[Constants::SEARCH_LOG_TABLE]
                ? (int) $this->db->getOne('SELECT COUNT(*) FROM ?n', Constants::SEARCH_LOG_TABLE)
                : null,
        ];

        $orphanNgrams = null;
        if ($tablePresence[Constants::SEARCH_INDEX_TABLE] && $tablePresence[Constants::SEARCH_NGRAMS_TABLE]) {
            $orphanNgrams = (int) $this->db->getOne(
                'SELECT COUNT(*)
                 FROM ?n AS sn
                 LEFT JOIN ?n AS si ON si.search_id = sn.search_id
                 WHERE si.search_id IS NULL',
                Constants::SEARCH_NGRAMS_TABLE,
                Constants::SEARCH_INDEX_TABLE
            );
            if ($orphanNgrams > 0) {
                $issues[] = 'Detected orphaned search ngrams.';
            }
        }

        return [
            'tables' => $tablePresence,
            'indexes' => $indexStatus,
            'counts' => $tableCounts,
            'orphan_ngrams' => $orphanNgrams,
            'issues' => $issues,
            'ok' => empty($issues),
        ];
    }

    /**
     * Возвращает EXPLAIN для основных поисковых запросов.
     * Нужен для проверки, что FULLTEXT/NGRAM индексы реально участвуют в плане.
     */
    public function explainSearchPlans(
        string $query,
        string $lang = ENV_DEF_LANG,
        bool $isAdminSearch = false,
        int $limit = 20,
        int $offset = 0
    ): array {
        $originalQuery = trim($query);
        if ($originalQuery === '') {
            return ['query' => $query, 'issues' => ['empty_query']];
        }

        $searchQueryPrepared = self::prepareSearchQueryBoolean($originalQuery);
        $entityTypeFilter = $isAdminSearch ? '' : "AND entity_type IN ('page', 'category')";
        $ngrams = self::generateNgrams(self::prepareContent($originalQuery));
        $safeSearchQuery = "'" . addslashes($searchQueryPrepared) . "'";

        $plans = [
            'query' => $originalQuery,
            'prepared_boolean_query' => $searchQueryPrepared,
            'ngram_count' => count($ngrams),
            'fulltext_count' => [],
            'fulltext_results' => [],
            'ngram_results' => [],
            'issues' => [],
        ];

        if ($searchQueryPrepared === '') {
            $plans['issues'][] = 'prepared_boolean_query_is_empty';
            return $plans;
        }

        try {
            $plans['fulltext_count'] = $this->db->getAll(
                "EXPLAIN SELECT COUNT(search_id) FROM ?n
                 WHERE MATCH(title, content_full) AGAINST (" . $safeSearchQuery . " IN BOOLEAN MODE)
                   AND language_code = ?s " . $entityTypeFilter,
                Constants::SEARCH_INDEX_TABLE,
                $lang
            );

            $plans['fulltext_results'] = $this->db->getAll(
                "EXPLAIN SELECT search_id, entity_id, entity_type, title, popularity_score, static_rank, language_code,
                                MATCH(title, content_full) AGAINST (" . $safeSearchQuery . " IN BOOLEAN MODE) AS relevance
                   FROM ?n
                  WHERE MATCH(title, content_full) AGAINST (" . $safeSearchQuery . " IN BOOLEAN MODE)
                    AND language_code = ?s " . $entityTypeFilter . "
                  ORDER BY relevance DESC, popularity_score DESC, static_rank DESC
                  LIMIT ?i, ?i",
                Constants::SEARCH_INDEX_TABLE,
                $lang,
                $offset,
                $limit
            );

            if (!empty($ngrams)) {
                $plans['ngram_results'] = $this->db->getAll(
                    "EXPLAIN SELECT si.search_id, si.entity_id, si.entity_type, si.title, si.popularity_score, si.static_rank,
                                    (t.ngram_matches / ?i) AS relevance
                       FROM ?n si
                       JOIN (
                           SELECT search_id, COUNT(ngram_id) AS ngram_matches
                           FROM ?n
                           WHERE ngram IN (?a)
                           GROUP BY search_id
                       ) t ON si.search_id = t.search_id
                      WHERE si.language_code = ?s " . ($isAdminSearch ? '' : "AND si.entity_type IN ('page', 'category')") . "
                      ORDER BY relevance DESC, si.popularity_score DESC, si.static_rank DESC
                      LIMIT ?i",
                    max(1, count($ngrams)),
                    Constants::SEARCH_INDEX_TABLE,
                    Constants::SEARCH_NGRAMS_TABLE,
                    $ngrams,
                    $lang,
                    $limit
                );
            }
        } catch (\Throwable $e) {
            $plans['issues'][] = $e->getMessage();
        }

        return $plans;
    }

    /**
     * Невесомый smoke-тест движка без зависимости от реальных страниц/категорий.
     * Создает временные строки индекса, проверяет exact/fuzzy/autocomplete и затем очищает за собой.
     */
    public function runSmokeTest(string $lang = ENV_DEF_LANG): array {
        $token = 'codexsearch' . bin2hex(random_bytes(5));
        $fuzzyToken = substr($token, 0, -1) . (substr($token, -1) === 'z' ? 'y' : 'z');
        $prefix = substr($token, 0, 8);
        $baseId = random_int(1500000000, 1999999999);
        $fixtures = [
            [
                'entity_id' => $baseId,
                'entity_type' => 'page',
                'language_code' => $lang,
                'title' => $token . ' page smoke',
                'content_full' => 'Search smoke content for ' . $token . ' architecture index relevance',
            ],
            [
                'entity_id' => $baseId + 1,
                'entity_type' => 'category',
                'language_code' => $lang,
                'title' => $token . ' category smoke',
                'content_full' => 'Search smoke category body ' . $token . ' taxonomy tree',
            ],
        ];

        $created = [];
        $normalizedQueries = [
            self::normalizeQuery($token),
            self::normalizeQuery($fuzzyToken),
        ];

        try {
            foreach ($fixtures as $fixture) {
                if ($this->updateIndexEntry($fixture)) {
                    $created[] = [$fixture['entity_type'], (int) $fixture['entity_id']];
                }
            }

            $autocompleteResults = $this->autocomplete($prefix, $lang, 10);
            $exactResults = $this->search($token, $lang, 10, 0);
            $fuzzyResults = $this->search($fuzzyToken, $lang, 10, 0);

            $expectedIds = array_column($fixtures, 'entity_id');
            $exactIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $exactResults['results'] ?? []);
            $fuzzyIds = array_map(static fn(array $row): int => (int) ($row['id'] ?? 0), $fuzzyResults['results'] ?? []);
            $autocompleteTypes = array_map(static fn(array $row): string => (string) ($row['type'] ?? ''), $autocompleteResults);

            return [
                'token' => $token,
                'fuzzy_token' => $fuzzyToken,
                'prefix' => $prefix,
                'created_entries' => count($created),
                'exact_search' => $exactResults,
                'fuzzy_search' => $fuzzyResults,
                'autocomplete' => $autocompleteResults,
                'assertions' => [
                    'fixtures_created' => count($created) === count($fixtures),
                    'exact_hits_fixture' => count(array_intersect($expectedIds, $exactIds)) >= 1,
                    'fuzzy_hits_fixture' => count(array_intersect($expectedIds, $fuzzyIds)) >= 1,
                    'autocomplete_hits_index_titles' => count(array_filter(
                        $autocompleteTypes,
                        static fn(string $type): bool => in_array($type, ['page', 'category'], true)
                    )) >= 1,
                ],
            ];
        } finally {
            foreach ($created as [$entityType, $entityId]) {
                $this->removeIndexEntry((string) $entityType, (int) $entityId);
            }
            if (!empty($normalizedQueries)) {
                $this->db->query(
                    'DELETE FROM ?n WHERE normalized_query IN (?a) AND language_code = ?s',
                    Constants::SEARCH_LOG_TABLE,
                    $normalizedQueries,
                    $lang
                );
            }
        }
    }

    // === ПРИВАТНЫЕ МЕТОДЫ ДЛЯ ВЫПОЛНЕНИЯ ПОИСКА ===

    /**
     * Выполнение основного поиска (FULLTEXT)
     */
    private function executeFullTextSearch(string $searchQueryPrepared, bool $isAdminSearch, string $lang, int $limit, int $offset): array {
        $entityTypeFilter = (!$isAdminSearch) ? "AND entity_type IN ('page', 'category')" : '';
        $orderByClause = "relevance DESC, popularity_score DESC, static_rank DESC";
        
        // Вручную создаем безопасную строку, чтобы обойти ошибки SafeMySQL
        $safeSearchQuery = "'" . addslashes($searchQueryPrepared) . "'";

        // Запрос для подсчета
        $sqlTotal = "SELECT COUNT(search_id) FROM ?n
                   WHERE MATCH(title, content_full) AGAINST (" . $safeSearchQuery . " IN BOOLEAN MODE)
                     AND language_code = ?s " . $entityTypeFilter;

        $total = (int) $this->db->getOne($sqlTotal, Constants::SEARCH_INDEX_TABLE, $lang);

        $results = [];
        if ($total > 0) {
            // Запрос для получения результатов
            $sqlResults = "SELECT search_id, entity_id, entity_type, title, popularity_score, static_rank, language_code, url,
                                  MATCH(title, content_full) AGAINST (" . $safeSearchQuery . " IN BOOLEAN MODE) AS relevance
                             FROM ?n
                            WHERE MATCH(title, content_full) AGAINST (" . $safeSearchQuery . " IN BOOLEAN MODE)
                              AND language_code = ?s " . $entityTypeFilter .
                         " ORDER BY ?p LIMIT ?i, ?i";

            $results = $this->db->getAll(
                $sqlResults,
                Constants::SEARCH_INDEX_TABLE,
                $lang,
                $orderByClause,
                $offset,
                $limit
            );
        }
        return [$results ?: [], $total];
    }

/**
     * Обновляет N-граммы для записи в search_index (удаление + вставка в цикле с транзакцией)
     * Использует синтаксис INSERT INTO ?n SET ?u для каждой N-граммы
     * @param int $searchId ID записи в search_index
     * @param array $ngrams Массив строк N-грамм
     * @return void
     */
    private function updateNgramsInDb(int $searchId, array $ngrams): void {
        // SysClass::preFile('debug_ngrams_db', 'Inside updateNgramsInDb', ['searchId' => $searchId, 'ngram_count' => count($ngrams)]);
        try {
            // 1. Начинаем транзакцию
            $this->db->query("START TRANSACTION");

            // 2. Удаляем старые N-граммы
            $this->removeNgramsFromDb([$searchId]);
            // SysClass::preFile('debug_ngrams_db', 'After removeNgramsFromDb', ['searchId' => $searchId]);

            // 3. Вставляем новые N-граммы в цикле
            if (!empty($ngrams)) {
                $validNgrams = array_values(array_filter(
                    array_unique($ngrams),
                    static fn(string $ngram): bool => mb_strlen($ngram) === self::NGRAM_LENGTH
                ));
                foreach (array_chunk($validNgrams, 250) as $ngramChunk) {
                    $values = [];
                    foreach ($ngramChunk as $ngram) {
                        $values[] = $this->db->parse('(?s, ?i)', $ngram, $searchId);
                    }
                    if (!empty($values)) {
                        $this->db->query(
                            'INSERT INTO ?n (ngram, search_id) VALUES ?p',
                            Constants::SEARCH_NGRAMS_TABLE,
                            implode(', ', $values)
                        );
                    }
                }
            } else {
                // SysClass::preFile('debug_ngrams_db', 'No ngrams to insert', ['searchId' => $searchId]);
            }

            // 4. Завершаем транзакцию
            $this->db->query("COMMIT");

        } catch (\Throwable $e) {
            // 5. Откатываем транзакцию в случае ошибки
            $this->db->query("ROLLBACK");

            $errorDetails = ['searchId' => $searchId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
            Logger::error('search_ngram_error', "Ошибка updateNgramsInDb для searchId {$searchId}: " . $e->getMessage(), $errorDetails, [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
            Logger::warning('debug_ngrams_db_exception', 'EXCEPTION in updateNgramsInDb, ROLLED BACK', $errorDetails, [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => 'EXCEPTION in updateNgramsInDb, ROLLED BACK',
                'include_trace' => false,
            ]);
        }
    }

    // Метод removeNgramsFromDb остается без изменений
    private function removeNgramsFromDb(array $searchIds): void {
         if (!empty($searchIds)) {
             // Здесь ?n и ?a - стандартные и должны работать
             $this->db->query("DELETE FROM ?n WHERE search_id IN (?a)", Constants::SEARCH_NGRAMS_TABLE, $searchIds);
         }
    }

    private function mergeResults(array $primaryResults, array $secondaryResults, int $limit): array {
        $merged = $primaryResults;
        $existingIds = array_column($primaryResults, 'search_id'); // Используем search_id для уникальности

        foreach ($secondaryResults as $secondaryResult) {
            if (!in_array($secondaryResult['search_id'], $existingIds)) {
                $merged[] = $secondaryResult;
                $existingIds[] = $secondaryResult['search_id']; // Добавляем ID, чтобы избежать дублей из secondaryResults
                if (count($merged) >= $limit)
                    break; // Прерываем, если достигли лимита
            }
        }
        // Пересортировка не нужна, т.к. secondary добавляются только если primary мало
        return array_slice($merged, 0, $limit);
    }

    /**
     * Логирование поискового запроса
     */
    public function logSearchQuery(string $originalQuery, string $area, string $lang, string $normalizedQuery = ''): void {
        $normalizedQuery = $normalizedQuery ?: self::normalizeQuery($originalQuery);
        if (empty($normalizedQuery)) {
            return;
        }
        try {
            // Запрос переписан на более надежный синтаксис SET
            $sql = "INSERT INTO ?n SET 
                        query_text = ?s, 
                        normalized_query = ?s, 
                        area = ?s, 
                        language_code = ?s, 
                        last_searched = NOW()
                    ON DUPLICATE KEY UPDATE 
                        hit_count = hit_count + 1, 
                        last_searched = NOW(), 
                        query_text = VALUES(query_text)";
            
            $this->db->query($sql, Constants::SEARCH_LOG_TABLE, $originalQuery, $normalizedQuery, $area, $lang);
        } catch (\Exception $e) {
            Logger::error('search_log_error', 'Ошибка логирования поискового запроса: ' . $e->getMessage(), [], [
                'initiator' => __CLASS__ . '::' . __FUNCTION__,
                'details' => $e->getMessage(),
            ]);
        }
    }

    public static function normalizeQuery(string $query): string {
        $query = mb_strtolower(trim($query));
        $query = preg_replace('/[^\p{L}\p{N}\s\']/u', ' ', $query);
        return trim(preg_replace('/\s+/', ' ', $query));
    }

    public static function prepareSearchQueryBoolean(string $query): string {
        $normalized = self::normalizeQuery($query);
        $words = explode(' ', $normalized);
        $preparedWords = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= self::MIN_WORD_LENGTH) {
                $preparedWords[] = '+' . $word; // Обязательное вхождение
            }
        }
        if (empty($preparedWords))
            return $normalized; // Возвращаем нормализованный без операторов, если нет слов нужной длины
        return implode(' ', $preparedWords);
    }

    private function getAdminEditUrl(string $entityType, int $entityId): string {
        $baseUrl = rtrim(ENV_URL_SITE, '/') . '/admin/';
        $entityTypeLower = strtolower($entityType);
        $suffix = self::ADMIN_URL_SUFFIX_MAP[$entityTypeLower] ?? null;
        if ($suffix) {
            return $baseUrl . $suffix . $entityId;
        }
        // Fallback для неизвестных типов
        return $baseUrl . 'edit/type/' . $entityType . '/id/' . $entityId;
    }
    
    /**
     * Полностью перестраивает поисковый индекс
     * Этот метод удаляет все старые поисковые данные и заново индексирует
     * @return array Статистика по результатам индексации
     */
    public function rebuildAllIndex(): array {
        $stats = [
            'truncated_tables' => [],
            'pages_indexed' => 0,
            'categories_indexed' => 0,
            'batches' => 0,
            'errors' => 0,
            'error_log' => [],
            'started_at' => date('c'),
        ];
        $startedAt = microtime(true);

        try {
            // Шаг 1: Полная очистка старых данных.
            $this->db->query("SET FOREIGN_KEY_CHECKS=0");
            $this->db->query("TRUNCATE TABLE ?n", Constants::SEARCH_NGRAMS_TABLE);
            $stats['truncated_tables'][] = Constants::SEARCH_NGRAMS_TABLE;
            $this->db->query("TRUNCATE TABLE ?n", Constants::SEARCH_INDEX_TABLE);
            $stats['truncated_tables'][] = Constants::SEARCH_INDEX_TABLE;
            $this->db->query("SET FOREIGN_KEY_CHECKS=1");

            // Шаг 2-3: Батчевая индексация активных страниц и категорий.
            $this->rebuildEntityTypeIndex('page', $stats);
            $this->rebuildEntityTypeIndex('category', $stats);

        } catch (\Throwable $e) {
            Logger::critical('rebuild_index_error', 'Критическая ошибка при полной переиндексации: ' . $e->getMessage(), [], [
                'initiator' => __CLASS__,
                'details' => $e->getMessage(),
            ]);
            $stats['fatal_error'] = $e->getMessage();
        }

        $stats['finished_at'] = date('c');
        $stats['duration_ms'] = (int) round((microtime(true) - $startedAt) * 1000);

        return $stats;
    }

    private static function prepareNgramSource(string $title, string $contentFull): string {
        $preparedTitle = self::prepareContent($title);
        $preparedContent = self::prepareContent($contentFull);
        if ($preparedContent !== '' && mb_strlen($preparedContent) > self::MAX_NGRAM_SOURCE_LENGTH) {
            $preparedContent = mb_substr($preparedContent, 0, self::MAX_NGRAM_SOURCE_LENGTH);
        }
        return trim($preparedTitle . ' ' . $preparedContent);
    }

    private function loadEntityRow(string $entityType, int $entityId, ?string $languageCode = null): ?array {
        $table = $entityType === 'page' ? Constants::PAGES_TABLE : Constants::CATEGORIES_TABLE;
        $idColumn = $entityType === 'page' ? 'page_id' : 'category_id';
        $languageSql = $languageCode !== null && $languageCode !== ''
            ? $this->db->parse(' AND language_code = ?s', $languageCode)
            : '';

        return $this->db->getRow(
            "SELECT {$idColumn}, title, short_description, description, status, language_code
             FROM ?n
             WHERE {$idColumn} = ?i{$languageSql}
             LIMIT 1",
            $table,
            $entityId
        ) ?: null;
    }

    private function buildIndexPayloadFromEntityRow(string $entityType, array $entityRow, ?string $propertyText = null): ?array {
        $idColumn = $entityType === 'page' ? 'page_id' : 'category_id';
        $entityId = (int) ($entityRow[$idColumn] ?? 0);
        if ($entityId <= 0) {
            return null;
        }

        $languageCode = (string) ($entityRow['language_code'] ?? ENV_DEF_LANG);
        $title = self::prepareTitle((string) ($entityRow['title'] ?? ''));
        if ($propertyText === null) {
            $propertyText = $this->getEntityPropertySearchText($entityType, $entityId, $languageCode);
        }

        $contentParts = [
            (string) ($entityRow['title'] ?? ''),
            (string) ($entityRow['short_description'] ?? ''),
            (string) ($entityRow['description'] ?? ''),
            $propertyText,
        ];
        $contentFull = self::prepareContent(implode(' ', array_filter(
            $contentParts,
            static fn($value): bool => is_scalar($value) && trim((string) $value) !== ''
        )));

        if ($title === '' && $contentFull === '') {
            return null;
        }

        return [
            'entity_id' => $entityId,
            'entity_type' => $entityType,
            'language_code' => $languageCode,
            'title' => $title,
            'content_full' => $contentFull,
            'url' => EntityPublicUrlService::buildEntityUrl($entityType, $entityId, $languageCode),
        ];
    }

    private function buildSearchTextFromRuntimeFields(array $runtimeFields): string {
        $parts = [];
        foreach ($runtimeFields as $field) {
            if (!is_array($field)) {
                continue;
            }
            $fieldType = strtolower(trim((string) ($field['type'] ?? '')));
            if (!in_array($fieldType, self::SEARCHABLE_FIELD_TYPES, true)) {
                continue;
            }

            if (PropertyFieldContract::isChoiceType($fieldType)) {
                $selectedKeys = array_map('strval', (array) ($field['value'] ?? []));
                if (empty($selectedKeys)) {
                    continue;
                }
                $optionByKey = [];
                foreach ((array) ($field['options'] ?? []) as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $key = trim((string) ($option['key'] ?? ''));
                    if ($key === '') {
                        continue;
                    }
                    $optionByKey[$key] = trim((string) ($option['label'] ?? $key));
                }
                foreach ($selectedKeys as $selectedKey) {
                    if ($selectedKey === '') {
                        continue;
                    }
                    if (isset($optionByKey[$selectedKey]) && $optionByKey[$selectedKey] !== '') {
                        $parts[] = $optionByKey[$selectedKey];
                    }
                    if (!isset($optionByKey[$selectedKey]) || $optionByKey[$selectedKey] !== $selectedKey) {
                        $parts[] = $selectedKey;
                    }
                }
                continue;
            }

            $value = $field['value'] ?? '';
            if (is_array($value)) {
                foreach ($value as $item) {
                    $item = trim((string) $item);
                    if ($item !== '') {
                        $parts[] = $item;
                    }
                }
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        return self::prepareContent(implode(' ', $parts));
    }

    private function rebuildEntityTypeIndex(string $entityType, array &$stats): void {
        $table = $entityType === 'page' ? Constants::PAGES_TABLE : Constants::CATEGORIES_TABLE;
        $idColumn = $entityType === 'page' ? 'page_id' : 'category_id';
        $statsKey = $entityType === 'page' ? 'pages_indexed' : 'categories_indexed';
        $lastId = 0;

        while (true) {
            $rows = $this->db->getAll(
                "SELECT {$idColumn}, title, short_description, description, language_code, status
                 FROM ?n
                 WHERE {$idColumn} > ?i AND status = ?s
                 ORDER BY {$idColumn} ASC
                 LIMIT ?i",
                $table,
                $lastId,
                'active',
                self::REBUILD_BATCH_SIZE
            );

            if (empty($rows)) {
                break;
            }

            $stats['batches']++;
            $rowsByLanguage = [];
            foreach ($rows as $row) {
                $languageCode = (string) ($row['language_code'] ?? ENV_DEF_LANG);
                $rowsByLanguage[$languageCode][] = $row;
            }

            $propertyTextMap = [];
            foreach ($rowsByLanguage as $languageCode => $languageRows) {
                $ids = array_map(static fn(array $row): int => (int) ($row[$idColumn] ?? 0), $languageRows);
                $propertyTextMap[$languageCode] = $this->collectEntityPropertySearchTextMap($entityType, $ids, $languageCode);
            }

            foreach ($rows as $row) {
                $entityId = (int) ($row[$idColumn] ?? 0);
                if ($entityId <= 0) {
                    continue;
                }
                $languageCode = (string) ($row['language_code'] ?? ENV_DEF_LANG);
                $propertyText = $propertyTextMap[$languageCode][$entityId] ?? '';
                $indexData = $this->buildIndexPayloadFromEntityRow($entityType, $row, $propertyText);
                if ($indexData === null || !$this->updateIndexEntry($indexData)) {
                    $stats['errors']++;
                    $stats['error_log'][] = "Failed to index {$entityType} with ID: {$entityId}";
                } else {
                    $stats[$statsKey]++;
                }
                $lastId = $entityId;
            }
        }
    }
    
}
