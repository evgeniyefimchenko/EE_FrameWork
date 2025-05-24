<?php
// 1. Подключаем загрузчик окружения
require_once __DIR__ . '/bootstrap.php';

// Используем классы
use classes\plugins\SafeMySQL;
use classes\helpers\ClassSearchEngine;
use classes\system\Constants;
use classes\system\ErrorLogger;

echo "===== Starting Search Popularity Update (" . date('Y-m-d H:i:s') . ") =====\n";

try {
    $db = SafeMySQL::gi();

    // --- Параметры ---
    $minHits = 3;       // Мин кол-во запросов в логе для учета
    $queryLimit = 100;  // Макс кол-во популярных запросов для обработки
    $resultLimit = 5;   // Скольким топ-результатам повышать рейтинг
    $decayFactor = 0.95; // Фактор затухания (5% уменьшение за запуск)

    // 1. Получаем популярные запросы
    echo "Fetching popular queries (hits >= {$minHits})...\n";
    $popularQueries = $db->getAll(
        "SELECT normalized_query, hit_count FROM ?n WHERE hit_count >= ?i ORDER BY hit_count DESC LIMIT ?i",
        Constants::SEARCH_LOG_TABLE, $minHits, $queryLimit
    );

    if (empty($popularQueries)) {
        echo "No popular queries found meeting criteria (>= {$minHits} hits).\n";
    } else {
        echo count($popularQueries) . " popular queries found. Processing...\n";
        $totalUpdated = 0;

        // 2. Обновляем очки для результатов этих запросов
        foreach ($popularQueries as $popQuery) {
            $normalizedQuery = $popQuery['normalized_query'];
            $hits = (int)$popQuery['hit_count'];

            // === Используем ЛОГАРИФМИЧЕСКУЮ шкалу ===
            // Добавляем +1 перед логарифмом, ceil() округляет вверх >= 1
            $scoreFactor = ceil(log($hits + 1));
            if ($scoreFactor < 1) $scoreFactor = 1; // Гарантируем минимум +1
            // ========================================

            // Готовим запрос для MATCH AGAINST
            $booleanQuery = ClassSearchEngine::prepareSearchQueryBoolean($normalizedQuery);
            if (empty($booleanQuery)) {
                echo "  Skipping empty boolean query for '{$normalizedQuery}'.\n";
                continue;
            }
            // Находим топ N результатов для этого запроса
            $topResults = $db->getCol(
                 "SELECT search_id FROM ?n
                  WHERE MATCH(title, content_full) AGAINST (? IN BOOLEAN MODE)
                  ORDER BY MATCH(title, content_full) AGAINST (? IN BOOLEAN MODE) DESC
                  LIMIT ?i",
                 Constants::SEARCH_INDEX_TABLE, $booleanQuery, $booleanQuery, $resultLimit
            );
            if (!empty($topResults)) {
                 $db->query( // Запрос на обновление
                     "UPDATE ?n SET popularity_score = popularity_score + ?i WHERE search_id IN (?a)",
                     Constants::SEARCH_INDEX_TABLE, $scoreFactor, $topResults
                 );
                 $affected = $db->affectedRows();
                 echo "  Query '{$normalizedQuery}' (Hits: {$hits}, Score: +{$scoreFactor}): Updated score for " . $affected . " results.\n";
                 $totalUpdated += $affected;
            } else {
                 echo "  Query '{$normalizedQuery}': No relevant results found in index.\n";
            }
        }
         echo "Total score increments applied: {$totalUpdated}.\n";
    }
    // 3. Применяем Затухание ко ВСЕМ записям с положительным рейтингом
    echo "Applying popularity decay (factor: {$decayFactor})...\n";
    $sqlDecay = $db->parse(
        "UPDATE ?n SET popularity_score = FLOOR(popularity_score * " . (float)$decayFactor . ")
         WHERE popularity_score > 0",
        Constants::SEARCH_INDEX_TABLE
    );
    $decayQuery = $db->query($sqlDecay);
    $decayedRows = $db->affectedRows();
    echo "Decayed scores applied to {$decayedRows} rows.\n";
    echo "===== Finished (" . date('Y-m-d H:i:s') . ") =====\n";
    exit(0);
} catch (\Throwable $e) {
    $errorMsg = 'CRON Error (' . basename(__FILE__) . '): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($errorMsg);
    try { new ErrorLogger($errorMsg, basename(__FILE__, '.php'), 'cron_error'); } catch (\Throwable $t) {}
    echo $errorMsg . "\n";
    exit(1);
}
