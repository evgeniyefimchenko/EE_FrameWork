(function($) {
    // Preloader
    jQuery(window).on('load', function() {
        $('.preloader').fadeOut();
    });

    // Инициализация при загрузке документа
    $(document).ready(function() {
        // Обработка отправки формы запуска цикла
        $('#start-betting-form').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const initialBalance = parseFloat(formData.get('initial_balance'));
            const strategy = formData.get('strategy');
            const email = formData.get('email');

            // Валидация на стороне клиента
            if (isNaN(initialBalance) || initialBalance < 100) {
                alert(AppCore.getLangVar('sys.invalid_balance') || 'Initial balance must be at least 100');
                return;
            }
            if (!['fixed', 'proportional', 'martingale'].includes(strategy)) {
                alert(AppCore.getLangVar('sys.invalid_strategy') || 'Invalid strategy selected');
                return;
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                alert(AppCore.getLangVar('sys.invalid_mail_format') || 'Invalid email format');
                return;
            }

            // Отправка AJAX-запроса
            AppCore.sendAjaxRequest(
                '/betting/start',
                formData,
                'POST',
                'json',
                function(response) {
                    const data = AppCore.safeParseJSON(response);
                    if (data.error) {
                        alert(data.error);
                    } else {
                        window.location.href = data.redirect || '/betting';
                    }
                },
                function(jqXHR, textStatus, errorThrown) {
                    console.error('Error starting betting cycle:', textStatus, errorThrown);
                    alert(AppCore.getLangVar('sys.ajax_error') || 'An error occurred. Please try again.');
                }
            );
        });

        // Построение графика баланса (если есть данные)
        const historyData = window.BETTING_HISTORY || []; // Предполагается, что данные переданы через PHP в глобальную переменную
        if (historyData.length > 0 && typeof Chart !== 'undefined') {
            const ctx = document.getElementById('balanceChart')?.getContext('2d');
            if (ctx) {
                const balances = [parseFloat($('#initial-balance').data('value')) || 0];
                const labels = ['Start'];

                historyData.forEach((bet, index) => {
                    balances.push(balances[index] - bet.bet_amount + (bet.balance_change || 0));
                    labels.push(bet.bet_date);
                });

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: AppCore.getLangVar('sys.balance') || 'Balance',
                            data: balances,
                            borderColor: '#007bff',
                            fill: false,
                            tension: 0.1
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: AppCore.getLangVar('sys.amount') || 'Amount'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: AppCore.getLangVar('sys.date') || 'Date'
                                }
                            }
                        }
                    }
                });
            }
        }

        // Обработка кнопки экспорта
        $('#export-history').on('click', function(e) {
            e.preventDefault();
            window.location.href = '/betting/export';
        });

        // Обработка кнопки сброса
        $('#reset-betting').on('click', function(e) {
            e.preventDefault();
            if (confirm(AppCore.getLangVar('sys.confirm_reset') || 'Are you sure you want to reset the betting cycle?')) {
                window.location.href = '/betting/reset';
            }
        });
    });
})(jQuery);