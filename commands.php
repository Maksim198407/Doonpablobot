<?php

$token = 'YOUR_BOT_TOKEN';
$url = "https://api.telegram.org/bot7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4/setMyCommands";

// Список доступных команд
$commands = [
    ['command' => '', 'description' => 'Запуск бота'],
    ['command' => 'menu', 'description' => 'Открыть меню'],
    ['command' => 'help', 'description' => 'Помощь'],
    ['command' => 'info', 'description' => 'Информация о боте'],
];

// Формируем данные
$data = [
    'commands' => json_encode($commands, JSON_UNESCAPED_UNICODE),
];

// Отправляем запрос в Telegram API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
$result = curl_exec($ch);
curl_close($ch);

echo $result;  // Выведет JSON-ответ от Telegram ({"ok":true,...})
?>