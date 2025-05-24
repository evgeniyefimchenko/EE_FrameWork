<?php
namespace classes\models;

use classes\plugins\SafeMySQL;
use classes\system\Constants;
use classes\system\ErrorLogger;

/**
 * Модель для CRUD-операций с данными ставок, спортивными событиями и циклами
 */
class ModelBetting {

    private $db;

    public function __construct() {
        $this->db = SafeMySQL::gi();
        $this->createTablesIfNotExist();
    }

    /**
     * Создает необходимые таблицы в базе данных, если они отсутствуют
     */
    private function createTablesIfNotExist(): void {
        try {
            // Таблица cycles
            $this->db->query("
                CREATE TABLE IF NOT EXISTS ?n (
                    cycle_id VARCHAR(36) PRIMARY KEY,
                    initial_balance DECIMAL(10,2) NOT NULL,
                    current_balance DECIMAL(10,2) NOT NULL,
                    strategy VARCHAR(50) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    start_date DATETIME NOT NULL
                )",
                ENV_DB_PREF . 'cycles'
            );

            // Таблица bets
            $this->db->query("
                CREATE TABLE IF NOT EXISTS ?n (
                    bet_id INT AUTO_INCREMENT PRIMARY KEY,
                    cycle_id VARCHAR(36) NOT NULL,
                    bet_date DATETIME NOT NULL,
                    event_1 TEXT NOT NULL,
                    event_2 TEXT NOT NULL,
                    event_3 TEXT NOT NULL,
                    outcome_1 VARCHAR(50) NOT NULL,
                    outcome_2 VARCHAR(50) NOT NULL,
                    outcome_3 VARCHAR(50) NOT NULL,
                    odds_1 DECIMAL(5,2) NOT NULL,
                    odds_2 DECIMAL(5,2) NOT NULL,
                    odds_3 DECIMAL(5,2) NOT NULL,
                    total_odds DECIMAL(5,2) NOT NULL,
                    bet_amount DECIMAL(10,2) NOT NULL,
                    result ENUM('win', 'loss', 'pending') DEFAULT 'pending',
                    balance_change DECIMAL(10,2) DEFAULT 0.00,
                    FOREIGN KEY (cycle_id) REFERENCES ?n(cycle_id)
                )",
                ENV_DB_PREF . 'bets',
                ENV_DB_PREF . 'cycles'
            );

            // Таблица sports_events
            $this->db->query("
                CREATE TABLE IF NOT EXISTS ?n (
                    event_id VARCHAR(50) PRIMARY KEY,
                    event_name TEXT NOT NULL,
                    league VARCHAR(100) NOT NULL,
                    event_date DATETIME NOT NULL,
                    odds_1 DECIMAL(5,2) NOT NULL,
                    odds_x DECIMAL(5,2),
                    odds_2 DECIMAL(5,2) NOT NULL,
                    result VARCHAR(50) DEFAULT NULL
                )",
                ENV_DB_PREF . 'sports_events'
            );
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка создания таблиц: " . $e->getMessage(), __FUNCTION__, 'betting');
            exit;
        }
    }

    // --- CRUD для таблицы cycles ---

    /**
     * Создает новый цикл
     * @param array $data Данные цикла (cycle_id, initial_balance, strategy, email)
     * @return string ID созданного цикла
     */
    public function createCycle(array $data): string {
        try {
            $this->db->query(
                "INSERT INTO ?n (cycle_id, initial_balance, current_balance, strategy, email, start_date) 
                 VALUES (?s, ?n, ?n, ?s, ?s, NOW())",
                ENV_DB_PREF . 'cycles',
                $data['cycle_id'],
                $data['initial_balance'],
                $data['initial_balance'],
                $data['strategy'],
                $data['email']
            );
            return $data['cycle_id'];
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка создания цикла: " . $e->getMessage(), __FUNCTION__, 'betting');
            return '';
        }
    }

    /**
     * Получает данные текущего цикла
     * @return array|null Данные цикла или null, если цикла нет
     */
    public function getCurrentCycle(): ?array {
        return $this->db->getRow(
            "SELECT * FROM ?n ORDER BY start_date DESC LIMIT 1",
            ENV_DB_PREF . 'cycles'
        );
    }

    /**
     * Обновляет баланс цикла
     * @param string $cycle_id ID цикла
     * @param float $current_balance Новый баланс
     * @return bool Успешность обновления
     */
    public function updateCycleBalance(string $cycle_id, float $current_balance): bool {
        try {
            $this->db->query(
                "UPDATE ?n SET current_balance = ?n WHERE cycle_id = ?s",
                ENV_DB_PREF . 'cycles',
                $current_balance,
                $cycle_id
            );
            return true;
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка обновления баланса цикла: " . $e->getMessage(), __FUNCTION__, 'betting');
            return false;
        }
    }

    /**
     * Удаляет цикл и связанные ставки
     * @param string $cycle_id ID цикла
     * @return bool Успешность удаления
     */
    public function deleteCycle(string $cycle_id): bool {
        try {
            $this->db->query(
                "DELETE FROM ?n WHERE cycle_id = ?s",
                ENV_DB_PREF . 'bets',
                $cycle_id
            );
            $this->db->query(
                "DELETE FROM ?n WHERE cycle_id = ?s",
                ENV_DB_PREF . 'cycles',
                $cycle_id
            );
            return true;
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка удаления цикла: " . $e->getMessage(), __FUNCTION__, 'betting');
            return false;
        }
    }

    // --- CRUD для таблицы bets ---

    /**
     * Создает новую ставку
     * @param array $data Данные ставки
     * @return int ID созданной ставки
     */
    public function createBet(array $data): int {
        try {
            $this->db->query(
                "INSERT INTO ?n (cycle_id, bet_date, event_1, event_2, event_3, outcome_1, outcome_2, outcome_3, odds_1, odds_2, odds_3, total_odds, bet_amount) 
                 VALUES (?s, NOW(), ?s, ?s, ?s, ?s, ?s, ?s, ?n, ?n, ?n, ?n, ?n)",
                ENV_DB_PREF . 'bets',
                $data['cycle_id'],
                $data['event_1'], $data['event_2'], $data['event_3'],
                $data['outcome_1'], $data['outcome_2'], $data['outcome_3'],
                $data['odds_1'], $data['odds_2'], $data['odds_3'],
                $data['total_odds'],
                $data['bet_amount']
            );
            return $this->db->insertId();
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка создания ставки: " . $e->getMessage(), __FUNCTION__, 'betting');
            return 0;
        }
    }

    /**
     * Получает историю ставок для цикла
     * @param string $cycle_id ID цикла
     * @return array История ставок
     */
    public function getBets(string $cycle_id): array {
        return $this->db->getAll(
            "SELECT * FROM ?n WHERE cycle_id = ?s ORDER BY bet_date DESC",
            ENV_DB_PREF . 'bets',
            $cycle_id
        );
    }

    /**
     * Обновляет результат ставки
     * @param int $bet_id ID ставки
     * @param string $result Результат (win/loss)
     * @param float $balance_change Изменение баланса
     * @return bool Успешность обновления
     */
    public function updateBetResult(int $bet_id, string $result, float $balance_change): bool {
        try {
            $this->db->query(
                "UPDATE ?n SET result = ?s, balance_change = ?n WHERE bet_id = ?i",
                ENV_DB_PREF . 'bets',
                $result,
                $balance_change,
                $bet_id
            );
            return true;
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка обновления результата ставки: " . $e->getMessage(), __FUNCTION__, 'betting');
            return false;
        }
    }

    // --- CRUD для таблицы sports_events ---

    /**
     * Создает или обновляет спортивное событие
     * @param array $data Данные события
     * @return bool Успешность операции
     */
    public function createOrUpdateEvent(array $data): bool {
        try {
            $this->db->query(
                "INSERT INTO ?n (event_id, event_name, league, event_date, odds_1, odds_x, odds_2) 
                 VALUES (?s, ?s, ?s, ?s, ?n, ?n, ?n) 
                 ON DUPLICATE KEY UPDATE 
                 event_name = ?s, league = ?s, event_date = ?s, odds_1 = ?n, odds_x = ?n, odds_2 = ?n",
                ENV_DB_PREF . 'sports_events',
                $data['event_id'], $data['event_name'], $data['league'], $data['event_date'],
                $data['odds_1'], $data['odds_x'], $data['odds_2'],
                $data['event_name'], $data['league'], $data['event_date'],
                $data['odds_1'], $data['odds_x'], $data['odds_2']
            );
            return true;
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка создания/обновления события: " . $e->getMessage(), __FUNCTION__, 'betting');
            return false;
        }
    }

    /**
     * Получает список событий для выбора экспресса
     * @return array Список событий
     */
    public function getAvailableEvents(): array {
        return $this->db->getAll(
            "SELECT * FROM ?n WHERE event_date > NOW() AND result IS NULL AND (odds_1 < 1.3 OR odds_2 < 1.3) LIMIT 3",
            ENV_DB_PREF . 'sports_events'
        );
    }

    /**
     * Получает событие по ID
     * @param string $event_id ID события
     * @return array|null Данные события
     */
    public function getEvent(string $event_id): ?array {
        return $this->db->getRow(
            "SELECT * FROM ?n WHERE event_id = ?s",
            ENV_DB_PREF. 'sports_events',
            $event_id
        );
    }

    /**
     * Обновляет результат события
     * @param string $event_id ID события
     * @param string $result Результат
     * @return bool Успешность обновления
     */
    public function updateEventResult(string $event_id, string $result): bool {
        try {
            $this->db->query(
                "UPDATE ?n SET result = ?s WHERE event_id = ?s",
                ENV_DB_PREF . 'sports_events',
                $result,
                $event_id
            );
            return true;
        } catch (\Exception $e) {
            new ErrorLogger("Ошибка обновления результата события: " . $e->getMessage(), __FUNCTION__, 'betting');
            return false;
        }
    }
}