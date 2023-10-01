<?php
// Проверяем, были ли отправлены данные
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные из тела запроса
    $data = file_get_contents('php://input');
    $data = json_decode($data, true);

    // Форматируем данные для записи в файл
    $logEntry = sprintf(
        "Referrer: %s\nUser Agent: %s\nIP: %s\nTime Spent: %d ms\n\n",
        $data['referrer'],
        $data['userAgent'],
        $data['ip'],
        $data['spentTime']
    );

    // Записываем данные в файл
    file_put_contents(__DIR__ . '/log_runes.txt', $logEntry, FILE_APPEND);
}
?>
