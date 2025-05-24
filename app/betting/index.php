<?php

namespace classes\controllers;

use classes\system\ControllerBase;
use classes\system\SysClass;
use classes\system\Session;
use classes\system\Cookies;
use classes\helpers\ClassMail;
use classes\system\Constants;
use classes\system\ErrorLogger;

/**
 * Класс контроллера страниц ставок
 * API ключ: 9f17bde4d889937460cbcac2c85da2b7
 * https://the-odds-api.com/account/
 */
class ControllerIndex extends ControllerBase {

    private $apiKey = '9f17bde4d889937460cbcac2c85da2b7'; // Ключ API

    /**
     * Загрузка стандартных представлений и скриптов
     */
    private function getStandardViews(): void {
        $this->view->set('logged_in', $this->logged_in);
        $this->parameters_layout["add_script"] .= '<script src="' . $this->getPathController() . '/js/betting.js" type="text/javascript"></script>';
        $this->parameters_layout["add_script"] .= '<script src="/assets/js/chart.js"></script>';
        $this->parameters_layout["add_style"] .= '<link rel="stylesheet" type="text/css" href="' . $this->getPathController() . '/css/betting.css"/>';
    }

    /**
     * Главная страница сервиса ставок
     * @param array|null $params Параметры маршрута
     */
    public function index($params = null): void {
        if ($params) {
            SysClass::handleRedirect();
        }
        $this->loadModel('ModelBetting');
        $cycle = $this->models['ModelBetting']->getCurrentCycle();

        $this->getStandardViews();
        if (!$cycle) {
            $this->html = $this->view->read('v_betting_start');
        } else {
            $this->view->set('balance', $cycle['current_balance']);
            $this->view->set('history', $this->models['ModelBetting']->getBets($cycle['cycle_id']));
            $this->view->set('strategy', $cycle['strategy']);
            $this->html = $this->view->read('v_betting_dashboard');
        }

        $this->parameters_layout["title"] = "Betting Service - " . ENV_SITE_NAME;
        $this->parameters_layout["description"] = "Сервис автоматических ставок на спорт";
        $this->parameters_layout["keywords"] = "ставки, спорт, экспресс, betting";
        $this->parameters_layout["layout_content"] = $this->html;
        $this->showLayout($this->parameters_layout);
    }

    /**
     * Запуск нового цикла ставок
     * @param array|null $params Параметры маршрута
     */
    public function start($params = null): void {
        if ($params || !SysClass::isAjaxRequestFromSameSite()) {
            SysClass::handleRedirect();
        }
        $this->loadModel('ModelBetting');
        $post = SysClass::ee_cleanArray($_POST);
        
        if (!isset($post['initial_balance']) || $post['initial_balance'] < 100 || 
            !isset($post['strategy']) || !in_array($post['strategy'], ['fixed', 'proportional', 'martingale']) || 
            !isset($post['email']) || !SysClass::validEmail($post['email'])) {
            die(json_encode(['error' => 'Неверные данные формы']));
        }

        $cycle_id = uniqid();
        $this->models['ModelBetting']->createCycle([
            'cycle_id' => $cycle_id,
            'initial_balance' => (float)$post['initial_balance'],
            'strategy' => $post['strategy'],
            'email' => $post['email']
        ]);

        die(json_encode(['error' => '', 'redirect' => '/betting']));
    }

    /**
     * Сброс текущего цикла
     * @param array|null $params Параметры маршрута
     */
    public function reset($params = null): void {
        if ($params) {
            SysClass::handleRedirect();
        }
        $this->loadModel('ModelBetting');
        $this->models['ModelBetting']->deleteCycle($this->models['ModelBetting']->getCurrentCycle()['cycle_id']);
        SysClass::handleRedirect(200, '/betting');
    }

    /**
     * Экспорт истории в CSV
     * @param array|null $params Параметры маршрута
     */
    public function export($params = null): void {
        if ($params) {
            SysClass::handleRedirect();
        }
        $this->loadModel('ModelBetting');
        $cycle = $this->models['ModelBetting']->getCurrentCycle();
        if (!$cycle) {
            SysClass::handleRedirect(200, '/betting');
        }

        $history = $this->models['ModelBetting']->getBets($cycle['cycle_id']);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="betting_history_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Дата', 'Событие 1', 'Событие 2', 'Событие 3', 'Исход 1', 'Исход 2', 'Исход 3',
            'Коэф. 1', 'Коэф. 2', 'Коэф. 3', 'Итог. коэф.', 'Ставка', 'Результат', 'Изменение баланса'
        ]);
        foreach ($history as $row) {
            fputcsv($output, [
                $row['bet_date'], $row['event_1'], $row['event_2'], $row['event_3'],
                $row['outcome_1'], $row['outcome_2'], $row['outcome_3'],
                $row['odds_1'], $row['odds_2'], $row['odds_3'], $row['total_odds'],
                $row['bet_amount'], $row['result'], $row['balance_change']
            ]);
        }
        fclose($output);
        exit;
    }

    /**
     * Ежедневное обновление событий и ставок (вызывается через cron)
     */
    public function cronUpdate($params = null): void {
        if (!isset($_SERVER['HTTP_HOST']) || $_SERVER['HTTP_HOST'] !== 'localhost') { // Защита от прямого доступа
            exit;
        }
        $this->loadModel('ModelBetting');
        $cycle = $this->models['ModelBetting']->getCurrentCycle();
        if (!$cycle) {
            return;
        }

        // Обновление событий
        $this->fetchAndStoreEvents();

        // Создание новой ставки
        $this->generateExpressBet($cycle);

        // Проверка результатов
        $this->checkBetResults($cycle);
    }

    /**
     * Получение и сохранение событий из API
     */
    private function fetchAndStoreEvents(): void {
        $url = "https://api.the-odds-api.com/v4/sports/upcoming/odds/?apiKey={$this->apiKey}®ions=us&markets=h2h&oddsFormat=decimal";
        $response = @file_get_contents($url);
        if ($response === false) {
            new ErrorLogger("Ошибка запроса к API", __FUNCTION__, 'betting');
            return;
        }
        $events = json_decode($response, true);
        if (!$events) {
            new ErrorLogger("Ошибка декодирования JSON из API", __FUNCTION__, 'betting');
            return;
        }

        foreach ($events as $event) {
            $odds = $event['bookmakers'][0]['markets'][0]['outcomes'];
            $this->models['ModelBetting']->createOrUpdateEvent([
                'event_id' => $event['id'],
                'event_name' => $event['home_team'] . ' vs ' . $event['away_team'],
                'league' => $event['sport_title'],
                'event_date' => $event['commence_time'],
                'odds_1' => $odds[0]['price'],
                'odds_x' => isset($odds[2]) ? $odds[2]['price'] : null,
                'odds_2' => $odds[1]['price']
            ]);
        }
    }

    /**
     * Генерация экспресс-ставки
     * @param array $cycle Данные цикла
     */
    private function generateExpressBet(array $cycle): void {
        $events = $this->models['ModelBetting']->getAvailableEvents();
        if (count($events) < 3) {
            new ErrorLogger("Недостаточно событий для экспресса", __FUNCTION__, 'betting');
            return;
        }

        $bet_amount = $this->calculateBetAmount($cycle);
        if ($bet_amount > $cycle['current_balance']) {
            $this->sendNotification($cycle);
            return;
        }

        $total_odds = $events[0]['odds_1'] * $events[1]['odds_1'] * $events[2]['odds_1'];
        $this->models['ModelBetting']->createBet([
            'cycle_id' => $cycle['cycle_id'],
            'event_1' => $events[английский]0]['event_name'],
            'event_2' => $events[1]['event_name'],
            'event_3' => $events[2]['event_name'],
            'outcome_1' => 'Победа 1',
            'outcome_2' => 'Победа 1',
            'outcome_3' => 'Победа 1',
            'odds_1' => $events[0]['odds_1'],
            'odds_2' => $events[1]['odds_1'],
            'odds_3' => $events[2]['odds_1'],
            'total_odds' => $total_odds,
            'bet_amount' => $bet_amount
        ]);

        $new_balance = $cycle['current_balance'] - $bet_amount;
        $this->models['ModelBetting']->updateCycleBalance($cycle['cycle_id'], $new_balance);
    }

    /**
     * Расчет суммы ставки по стратегии
     * @param array $cycle Данные цикла
     * @return float Сумма ставки
     */
    private function calculateBetAmount(array $cycle): float {
        $last_bet = $this->models['ModelBetting']->getBets($cycle['cycle_id'])[0] ?? null;
        switch ($cycle['strategy']) {
            case 'fixed':
                return 100.00;
            case 'proportional':
                $total_odds = $last_bet ? $last_bet['total_odds'] : 1.0;
                return $cycle['current_balance'] / $total_odds;
            case 'martingale':
                if ($last_bet && $last_bet['result'] === 'loss') {
                    return $last_bet['bet_amount'] * 2;
                }
                return 100.00;
            default:
                return 100.00;
        }
    }

    /**
     * Проверка результатов ставок
     * @param array $cycle Данные цикла
     */
    private function checkBetResults(array $cycle): void {
        $bets = $this->models['ModelBetting']->getBets($cycle['cycle_id']);
        foreach ($bets as $bet) {
            if ($bet['result'] !== 'pending') {
                continue;
            }

            $events = [$bet['event_1'], $bet['event_2'], $bet['event_3']];
            $win = true;
            foreach ($events as $event_name) {
                $event = $this->models['ModelBetting']->getEvent($this->getEventIdByName($event_name));
                if (!$event['result']) {
                    $this->updateEventResult($event['event_id']);
                }
                if ($event['result'] !== 'Победа 1') {
                    $win = false;
                    break;
                }
            }

            $result = $win ? 'win' : 'loss';
            $balance_change = $win ? $bet['bet_amount'] * $bet['total_odds'] : 0;
            $this->models['ModelBetting']->updateBetResult($bet['bet_id'], $result, $balance_change);
            if ($win) {
                $new_balance = $cycle['current_balance'] + $balance_change;
                $this->models['ModelBetting']->updateCycleBalance($cycle['cycle_id'], $new_balance);
            }
        }
    }

    /**
     * Получение ID события по имени (временное решение)
     * @param string $event_name Название события
     * @return string ID события
     */
    private function getEventIdByName(string $event_name): string {
        return $this->models['ModelBetting']->getEvent(
            $this->models['ModelBetting']->getAvailableEvents()[0]['event_id']
        )['event_id']; // Упрощение для примера
    }

    /**
     * Обновление результата события через API
     * @param string $event_id ID события
     */
    private function updateEventResult(string $event_id): void {
        $url = "https://api.the-odds-api.com/v4/events/{$event_id}/scores?apiKey={$this->apiKey}";
        $response = @file_get_contents($url);
        if ($response) {
            $data = json_decode($response, true);
            $result = $data['scores'][0]['winner'] === 'home' ? 'Победа 1' : 'Победа 2';
            $this->models['ModelBetting']->updateEventResult($event_id, $result);
        }
    }

    /**
     * Отправка уведомления при исчерпании баланса
     * @param array $cycle Данные цикла
     */
    private function sendNotification(array $cycle): void {
        if ($cycle['current_balance'] <= 0) {
            $message = "Ваш баланс исчерпан. Текущий баланс: {$cycle['current_balance']}. Сервис остановлен.";
            ClassMail::sendMail($cycle['email'], 'Уведомление о балансе', $message);
            new ErrorLogger("Отправлено уведомление: $message", __FUNCTION__, 'betting', ['email' => $cycle['email']]);
        }
    }
}