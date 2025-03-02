<?php
// Токен вашего Telegram-бота
define("TG_TOKEN", "7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4");

// Функция для отправки запроса к Telegram API
function sendApiCommand($method, $params = []) {
    $url = "https://api.telegram.org/bot" . TG_TOKEN . "/" . $method . "?" . http_build_query($params);
    $response = file_get_contents($url);
    return json_decode($response, true);
}

// Формируем список команд
$commands = [
    [
        "command" => "menu",
        "description" => "Открыть главное меню"
    ],
    [
        "command" => "orders",
        "description" => "Корзина"
    ],
    [
        "command" => "help",
        "description" => "Поддержка"
    ],
];

// Отправляем запрос к методу setMyCommands для установки списка команд
$params = [
    "commands" => json_encode($commands)
];

$response = sendApiCommand("setMyCommands", $params);

// Выводим ответ API для проверки
echo "<pre>";
print_r($response);
echo "</pre>";
?>
