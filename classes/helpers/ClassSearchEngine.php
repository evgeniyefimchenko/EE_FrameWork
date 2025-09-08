<?php

namespace classes\helpers;

use \classes\plugins\SafeMySQL;
use \classes\system\SysClass;
use \classes\system\Constants;
use \classes\system\ErrorLogger;

/**
 * Класс для управления поисковым индексом и выполнения поиска по сайту
 */
class ClassSearchEngine {

    private $db;

    private const MIN_WORD_LENGTH = 3; // Минимальная длина слова для поиска/индексации
    private const NGRAM_LENGTH = 3;    // Длина N-граммы
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
                $textForNgrams = $indexData['title'] . ' ' . $indexData['content_full'];
                $ngrams = self::generateNgrams(self::prepareContent($textForNgrams));
                $this->updateNgramsInDb((int)$searchId, $ngrams);
            } else {
                 return false;
            }

            return true;
        } catch (\Throwable $e) {
            new ErrorLogger("Критическая ошибка updateIndexEntry: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'search_index_error', ['data' => $data]);
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
            new ErrorLogger("Ошибка removeIndexEntry {$entityType} ID {$entityId}: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'search_index_error', ['entity_type' => $entityType, 'entity_id' => $entityId]);
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
            new ErrorLogger("Ошибка SearchEngine::search: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'search_error', ['query' => $originalQuery, 'lang' => $lang]);
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
                    'url' => $this->getAdminEditUrl($row['entity_type'], $row['entity_id']),
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
                        "SELECT entity_id, entity_type, title FROM ?n
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
                        }
                        $suggestions[] = ['value' => $value, 'label' => $label, 'type' => $tm['entity_type'], 'url' => $url];
                        $processedValues[$value] = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            new ErrorLogger("Ошибка SearchEngine::autocomplete: " . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'search_error', ['term' => $term, 'lang' => $lang]);
            return [];
        }
        return $suggestions;
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
            $sqlResults = "SELECT search_id, entity_id, entity_type, title, popularity_score, static_rank, language_code,
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
                // --- ИСПОЛЬЗУЕМ СТАНДАРТНЫЙ INSERT ... SET ?u ---
                $sql = "INSERT INTO ?n SET ?u"; // Плейсхолдеры ?n и ?u
                $insertedCount = 0;
                foreach ($ngrams as $ngram) {
                    if (mb_strlen($ngram) === self::NGRAM_LENGTH) {
                         // Формируем массив данных для ?u
                         $data = [
                             'ngram'     => $ngram,
                             'search_id' => $searchId
                         ];
                         // Выполняем запрос с ?n и ?u
                         $this->db->query($sql, Constants::SEARCH_NGRAMS_TABLE, $data);
                         $insertedCount++;
                    }
                }
                // SysClass::preFile('debug_ngrams_db', 'Finished INSERTs loop', ['searchId' => $searchId, 'inserted_count' => $insertedCount, 'total_ngrams' => count($ngrams)]);
                 // ---------------------------------------------
            } else {
                // SysClass::preFile('debug_ngrams_db', 'No ngrams to insert', ['searchId' => $searchId]);
            }

            // 4. Завершаем транзакцию
            $this->db->query("COMMIT");

        } catch (\Throwable $e) {
            // 5. Откатываем транзакцию в случае ошибки
            $this->db->query("ROLLBACK");

            $errorDetails = ['searchId' => $searchId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()];
            new ErrorLogger("Ошибка updateNgramsInDb для searchId {$searchId}: " . $e->getMessage(), __CLASS__.'::'.__FUNCTION__, 'search_ngram_error', $errorDetails);
            SysClass::preFile('debug_ngrams_db_EXCEPTION', 'EXCEPTION in updateNgramsInDb, ROLLED BACK', $errorDetails);
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
            new ErrorLogger('Ошибка логирования поискового запроса: ' . $e->getMessage(), __CLASS__ . '::' . __FUNCTION__, 'search_log_error');
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
            'errors' => 0,
            'error_log' => []
        ];

        try {
            // Шаг 1: Полная очистка старых данных.
            $this->db->query("SET FOREIGN_KEY_CHECKS=0");
            $this->db->query("TRUNCATE TABLE ?n", Constants::SEARCH_NGRAMS_TABLE);
            $stats['truncated_tables'][] = Constants::SEARCH_NGRAMS_TABLE;
            $this->db->query("TRUNCATE TABLE ?n", Constants::SEARCH_INDEX_TABLE);
            $stats['truncated_tables'][] = Constants::SEARCH_INDEX_TABLE;
            $this->db->query("SET FOREIGN_KEY_CHECKS=1");

            // Шаг 2: Индексация всех активных страниц (pages).
            $pages = $this->db->getAll(
                "SELECT page_id, title, description, short_description FROM ?n WHERE status = 'active'",
                Constants::PAGES_TABLE
            );
            foreach ($pages as $page) {
                $contentForSearch = $page['title'] . ' ' . ($page['short_description'] ?? '') . ' ' . ($page['description'] ?? '');
                
                // ИСПРАВЛЕНО: URL больше не передается, как и в хуке.
                $pageData = [
                    'entity_id'    => $page['page_id'],
                    'entity_type'  => 'page',
                    'title'        => $page['title'],
                    'content_full' => $contentForSearch,
                ];

                if (!$this->updateIndexEntry($pageData)) {
                    $stats['errors']++;
                    $stats['error_log'][] = "Failed to index page with ID: " . $page['page_id'];
                } else {
                    $stats['pages_indexed']++;
                }
            }

            // Шаг 3: Индексация всех активных категорий (categories).
            $categories = $this->db->getAll(
                "SELECT category_id, title, description, short_description FROM ?n WHERE status = 'active'",
                Constants::CATEGORIES_TABLE
            );
            foreach ($categories as $category) {
                $contentForSearch = $category['title'] . ' ' . ($category['short_description'] ?? '') . ' ' . ($category['description'] ?? '');

                // ИСПРАВЛЕНО: URL больше не передается.
                $categoryData = [
                    'entity_id'    => $category['category_id'],
                    'entity_type'  => 'category',
                    'title'        => $category['title'],
                    'content_full' => $contentForSearch,
                ];

                if (!$this->updateIndexEntry($categoryData)) {
                    $stats['errors']++;
                    $stats['error_log'][] = "Failed to index category with ID: " . $category['category_id'];
                } else {
                    $stats['categories_indexed']++;
                }
            }

        } catch (\Throwable $e) {
            new ErrorLogger("Критическая ошибка при полной переиндексации: " . $e->getMessage(), __CLASS__, 'rebuild_index_error');
            $stats['fatal_error'] = $e->getMessage();
        }

        return $stats;
    }    
    
}
