<?php
namespace classes\helpers;

use SafeMySQL;
use classes\system\ErrorLogger;

/**
 * Класс для работы с системой поиска
 * Поддерживает индексацию, поиск с учетом релевантности, автодополнение и нечеткий поиск
 */
class ClassSearchEngine {
    const TABLE_NAME = 'search_contents';

    /**
     * Индексирует или обновляет сущность в таблице поиска
     * @param string $entityType Тип сущности (например, 'category', 'page')
     * @param int $entityId ID сущности
     * @param array $data Данные для индексации
     */
    public function indexEntity(string $entityType, int $entityId, array $data): void {
        try {
            $db = SafeMySQL::gi();
            $table = self::TABLE_NAME;

            // Подготовка данных
            $fullContent = trim(implode(' ', array_filter([
                $data['title'] ?? '',
                strip_tags($data['description'] ?? ''),
                strip_tags($data['short_description'] ?? '')
            ])));
            $shortContent = trim(strip_tags($data['title'] ?? ''));
            $relevanceScore = $this->calculateRelevance($data);

            // Проверка существующей записи
            $existing = $db->getOne(
                "SELECT search_id FROM ?n WHERE entity_id = ?i AND entity_type = ?s AND language_code = ?s AND area = ?s",
                $table, $entityId, $entityType, $data['language_code'], $data['area']
            );

            if ($existing) {
                $db->query(
                    "UPDATE ?n SET full_search_content = ?s, short_search_content = ?s, relevance_score = ?i WHERE search_id = ?i",
                    $table, $fullContent, $shortContent, $relevanceScore, $existing
                );
            } else {
                $db->query(
                    "INSERT INTO ?n (entity_id, entity_type, area, full_search_content, short_search_content, language_code, relevance_score) 
                    VALUES (?i, ?s, ?s, ?s, ?s, ?s, ?i)",
                    $table, $entityId, $entityType, $data['area'], $fullContent, $shortContent, $data['language_code'], $relevanceScore
                );
            }

            new ErrorLogger('Entity indexed', __FUNCTION__, 'search_info', [
                'entity_type' => $entityType, 'entity_id' => $entityId
            ]);
        } catch (\Exception $e) {
            new ErrorLogger('Error indexing entity: ' . $e->getMessage(), __FUNCTION__, 'search_error', [
                'entity_type' => $entityType, 'entity_id' => $entityId, 'data' => $data
            ]);
        }
    }

    /**
     * Вычисляет релевантность записи
     * @param array $data Данные сущности
     * @return int Оценка релевантности (0-255)
     */
    private function calculateRelevance(array $data): int {
        $score = 0;
        // Базовая релевантность по длине текста (короткие более релевантны)
        $length = mb_strlen($data['title'] ?? '');
        $score += min(50, max(0, 100 - $length * 2)); // До 50 баллов

        // Бонус за статус
        if (isset($data['status']) && $data['status'] === 'active') {
            $score += 30;
        } elseif (isset($data['status']) && $data['status'] === 'hidden') {
            $score += 10;
        }

        // Ограничение до 255 (TINYINT UNSIGNED)
        return min(255, $score);
    }

    /**
     * Выполняет поиск по запросу с учетом релевантности и фильтров
     * @param string $query Поисковый запрос
     * @param array $params Параметры (area, language_code, entity_types, limit)
     * @return array Результаты поиска
     */
    public function search(string $query, array $params = []): array {
        $limit = $params['limit'] ?? 10;
        $area = $params['area'] ?? 'A';
        $entityTypes = $params['entity_types'] ?? [];

        try {
            $db = SafeMySQL::gi();
            $table = self::TABLE_NAME;

            // Основной поиск
            $results = $this->exactSearch($query, $table, $params);
            if (!empty($results)) {
                return array_slice($results, 0, $limit);
            }

            // Нечеткий поиск
            return $this->fuzzySearch($query, $table, $params, $limit);
        } catch (\Exception $e) {
            new ErrorLogger('Search error: ' . $e->getMessage(), __FUNCTION__, 'search_error', [
                'query' => $query, 'params' => $params
            ]);
            return [];
        }
    }

    /**
     * Полнотекстовый поиск
     */
    private function exactSearch(string $query, string $table, array $params): array {
        $db = SafeMySQL::gi();
        $where = ['MATCH(full_search_content, short_search_content) AGAINST(?s IN BOOLEAN MODE)'];
        $bind = [$query];

        if (isset($params['area'])) {
            $where[] = 'area = ?s';
            $bind[] = $params['area'];
        }
        if (isset($params['language_code'])) {
            $where[] = 'language_code = ?s';
            $bind[] = $params['language_code'];
        }
        if (!empty($params['entity_types'])) {
            $where[] = 'entity_type IN (' . implode(',', array_fill(0, count($params['entity_types']), '?s')) . ')';
            $bind = array_merge($bind, $params['entity_types']);
        }

        $sql = "SELECT search_id, entity_id, entity_type, short_search_content, relevance_score 
                FROM ?n WHERE " . implode(' AND ', $where) . " 
                ORDER BY relevance_score DESC, MATCH(full_search_content, short_search_content) AGAINST(?s) DESC 
                LIMIT 50";
        array_push($bind, $query);
        return $db->getAll($sql, $table, ...$bind);
    }

    /**
     * Нечеткий поиск с использованием расстояния Дамерау-Левенштейна
     */
    private function fuzzySearch(string $query, string $table, array $params, int $limit): array {
        $db = SafeMySQL::gi();
        $where = ['1=1'];
        $bind = [];

        if (isset($params['area'])) {
            $where[] = 'area = ?s';
            $bind[] = $params['area'];
        }
        if (isset($params['language_code'])) {
            $where[] = 'language_code = ?s';
            $bind[] = $params['language_code'];
        }
        if (!empty($params['entity_types'])) {
            $where[] = 'entity_type IN (' . implode(',', array_fill(0, count($params['entity_types']), '?s')) . ')';
            $bind = array_merge($bind, $params['entity_types']);
        }

        $sql = "SELECT search_id, entity_id, entity_type, short_search_content, relevance_score 
                FROM ?n WHERE " . implode(' AND ', $where) . " LIMIT 100";
        $candidates = $db->getAll($sql, $table, ...$bind);

        $results = [];
        foreach ($candidates as $candidate) {
            $distance = $this->damerauLevenshtein($query, $candidate['short_search_content']);
            if ($distance <= max(2, mb_strlen($query) / 3)) { // Допускаем до 33% ошибок
                $results[] = array_merge($candidate, ['distance' => $distance]);
            }
        }
        usort($results, fn($a, $b) => $a['distance'] <=> $b['distance'] ?: $b['relevance_score'] <=> $a['relevance_score']);
        return array_slice($results, 0, $limit);
    }

    /**
     * Вычисляет расстояние Дамерау-Левенштейна между строками
     */
    private function damerauLevenshtein(string $str1, string $str2): int {
        $len1 = mb_strlen($str1);
        $len2 = mb_strlen($str2);
        $d = array_fill(0, $len1 + 1, array_fill(0, $len2 + 1, 0));

        for ($i = 0; $i <= $len1; $i++) $d[$i][0] = $i;
        for ($j = 0; $j <= $len2; $j++) $d[0][$j] = $j;

        for ($i = 1; $i <= $len1; $i++) {
            for ($j = 1; $j <= $len2; $j++) {
                $cost = mb_substr($str1, $i - 1, 1) === mb_substr($str2, $j - 1, 1) ? 0 : 1;
                $d[$i][$j] = min(
                    $d[$i - 1][$j] + 1, // удаление
                    $d[$i][$j - 1] + 1, // вставка
                    $d[$i - 1][$j - 1] + $cost // замена
                );
                if ($i > 1 && $j > 1 && mb_substr($str1, $i - 1, 1) === mb_substr($str2, $j - 2, 1) && 
                    mb_substr($str1, $i - 2, 1) === mb_substr($str2, $j - 1, 1)) {
                    $d[$i][$j] = min($d[$i][$j], $d[$i - 2][$j - 2] + 1); // перестановка
                }
            }
        }
        return $d[$len1][$len2];
    }

    /**
     * Автодополнение для AJAX-запросов
     * @param string $query Частичный запрос
     * @param array $params Параметры (area, language_code, entity_types)
     * @return array Подсказки
     */
    public function suggest(string $query, array $params = []): array {
        try {
            $db = SafeMySQL::gi();
            $table = self::TABLE_NAME;
            $queryLower = mb_strtolower($query);

            $where = ['short_search_content LIKE ?s'];
            $bind = ["$queryLower%"];

            if (isset($params['area'])) {
                $where[] = 'area = ?s';
                $bind[] = $params['area'];
            }
            if (isset($params['language_code'])) {
                $where[] = 'language_code = ?s';
                $bind[] = $params['language_code'];
            }
            if (!empty($params['entity_types'])) {
                $where[] = 'entity_type IN (' . implode(',', array_fill(0, count($params['entity_types']), '?s')) . ')';
                $bind = array_merge($bind, $params['entity_types']);
            }

            $sql = "SELECT DISTINCT short_search_content 
                    FROM ?n 
                    WHERE " . implode(' AND ', $where) . " 
                    ORDER BY relevance_score DESC 
                    LIMIT 10";
            return $db->getCol($sql, $table, ...$bind);
        } catch (\Exception $e) {
            new ErrorLogger('Suggestion error: ' . $e->getMessage(), __FUNCTION__, 'search_error', [
                'query' => $query, 'params' => $params
            ]);
            return [];
        }
    }
}