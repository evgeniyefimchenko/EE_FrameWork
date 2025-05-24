<?php
/** @var classes\system\View $this */
?>

<div class="betting-container betting-dashboard">
    <h2>
        <i class="fas fa-wallet"></i> 
        Баланс: 
        <span class="balance" id="initial-balance" data-value="<?php echo $this->get('balance'); ?>">
            <?php echo number_format($this->get('balance'), 2); ?>
        </span>
    </h2>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Дата</th>
                <th>Событие 1</th>
                <th>Событие 2</th>
                <th>Событие 3</th>
                <th>Сумма ставки</th>
                <th>Итоговый коэффициент</th>
                <th>Результат</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->get('history') as $bet): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($bet['bet_date'])); ?></td>
                    <td><?php echo htmlspecialchars($bet['event_1']); ?></td>
                    <td><?php echo htmlspecialchars($bet['event_2']); ?></td>
                    <td><?php echo htmlspecialchars($bet['event_3']); ?></td>
                    <td><?php echo number_format($bet['bet_amount'], 2); ?></td>
                    <td><?php echo number_format($bet['total_odds'], 2); ?></td>
                    <td>
                        <?php 
                        if ($bet['result'] === 'win') {
                            echo '<span class="badge bg-success">Победа</span>';
                        } elseif ($bet['result'] === 'loss') {
                            echo '<span class="badge bg-danger">Поражение</span>';
                        } else {
                            echo '<span class="badge bg-warning">Ожидание</span>';
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($this->get('history'))): ?>
                <tr>
                    <td colspan="7" class="text-center">Ставок пока нет</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="chart-container">
        <canvas id="balanceChart"></canvas>
    </div>

    <div class="button-group">
        <button id="export-history" class="btn btn-secondary">
            <i class="fas fa-download"></i> 
            Экспорт истории
        </button>
        <button id="reset-betting" class="btn btn-danger">
            <i class="fas fa-trash"></i> 
            Сброс
        </button>
    </div>
</div>

<script>
    window.BETTING_HISTORY = <?php echo json_encode($this->get('history')); ?>;
</script>