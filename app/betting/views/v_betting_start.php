<div class="betting-container">
    <h2>Запуск ставок</h2>
    <form id="start-betting-form">
        <div class="form-group">
            <label for="initial_balance">
                <i class="fas fa-money-bill-wave"></i> 
                Начальная сумма
            </label>
            <input type="number" name="initial_balance" id="initial_balance" min="100" step="1" class="form-control" 
                   placeholder="Введите сумму" required>
        </div>
        <div class="form-group">
            <label for="strategy">
                <i class="fas fa-cogs"></i> 
                Стратегия
            </label>
            <select name="strategy" id="strategy" class="form-control" required>
                <option value="fixed">Фиксированная</option>
                <option value="proportional">Пропорциональная</option>
                <option value="martingale">Мартингейл</option>
            </select>
        </div>
        <div class="form-group">
            <label for="email">
                <i class="fas fa-envelope"></i> 
                Email
            </label>
            <input type="email" name="email" id="email" class="form-control" 
                   placeholder="Введите ваш email" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-play"></i> 
            Запустить
        </button>
    </form>
</div>