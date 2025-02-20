<?php

set_time_limit(0); // Отключаем ограничение по времени

define("TG_TOKEN", "7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4");
define("OFFSET_FILE", "last_update.txt"); // Файл для хранения последнего update_id

// Читаем последний обработанный update_id (если есть)
$lastUpdateId = 0;
if (file_exists(OFFSET_FILE)) {
    $lastUpdateId = (int) file_get_contents(OFFSET_FILE);
}

while (true) {
    // Получаем ТОЛЬКО новые сообщения
    $urlQueryTE = "https://api.telegram.org/bot7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4/getUpdates?offset=" . ($lastUpdateId + 1);

    // Инициализация cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlQueryTE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Получать ответ как строку
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Временно отключаем проверку сертификата
    $response = curl_exec($ch); // Выполняем запрос
    if (curl_errno($ch)) {
        echo 'cURL error: ' . curl_error($ch);
    }

    curl_close($ch); // Закрываем сессию

    $data = json_decode($response, true);

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $updateId = $update['update_id'];

            // Проверяем, чтобы update_id был больше последнего сохраненного
            if ($updateId > $lastUpdateId) {
                $lastUpdateId = $updateId; // Обновляем последний update_id

                // ОБРАБОТКА СООБЩЕНИЙ //
                // Инициализация переменной callbackData/TG_USER_ID
                $callbackData = null;
                $TG_USER_ID = null;
                if (isset($update['message']['text']) && isset($update['message']['chat']['id'])) {
                    $textMessageR = $update['message']['text']; // Текст сообщения
                    $TG_USER_ID = $update['message']['chat']['id']; // ID пользователя
                    $messageId = $update['message']['message_id'];
                    echo "Получено сообщение: " . $textMessageR . "\n";
                    // Проверка на наличие callback_query
                    if (isset($update['callback_query']) && isset($update['callback_query']['data'])) {
                        $TG_USER_ID = $update['callback_query']['chat']['id'];
                        $callbackData = $update['callback_query']['data'];  // Получаем данные callback
                    }
                   

                    // Команда /info
                    if ($textMessageR === "/info") {
                        $data = [
                            'chat_id' => $TG_USER_ID,
                            'text' => "Этот бот позволяет выбирать товары, добавлять их в корзину, оплачивать и получать официальные чеки. Он также уведомляет вас и администратора о статусе заказа."
                        ];
                        sendMessage($data); // Отправляем сообщение пользователю
                    }
                    
                    
                    // Команда /menu
                    if ($textMessageR === "/menu") {
                        $keyboard = [
                            "inline_keyboard" => [
                                [
                                    ["text" => "Пицца", "callback_data" => "pizza"],
                                    ["text" => "Бургер", "callback_data" => "burger"],
                                    ["text" => "Суши", "callback_data" => "sushi"],
                                    ["text" => "Напитки", "callback_data" => "drinks"]
                                ],
                                [
                                    ["text" => "Наш сайт", "url" => "https://www.youtube.com/watch?v=jfKfPfyJRdk"],
                                    ["text" => "Поддержка", "url" => "https://www.youtube.com/watch?v=jfKfPfyJRdk"]
                                ]
                            ]
                        ];

                        $data = [
                            'chat_id' => $TG_USER_ID,
                            'text' => "Выберите что хотите взять:",
                            'reply_markup' => json_encode($keyboard),
                        ];
                        sendMessage($data); // Отправляем меню пользователю
                    }
                }
            
                // Обработка callback_query, если есть

                // Инициализация переменной перед проверкой
                $responseText = ''; // Убедитесь, что переменная инициализирована перед использованием

                if ($callbackData !== null) {
                
                    switch ($callbackData) {
                        case "pizza":
                            $responseText = "Вы выбрали пиццу!";
                            break;
                        case "burger":
                            $responseText = "Вы выбрали бургер!";
                            break;
                        case "sushi":
                            $responseText = "Вы выбрали суши!";
                            break;
                        case "drinks":
                            $responseText = "Вы выбрали напитки!";
                            break;

                    }
                }

                    // Редактируем сообщение с выбранным товаром
                    $data = [
                        'chat_id' => $TG_USER_ID,
                        'message_id' => $messageId,
                        'text' => $responseText,
                    ];
                    sendMessage($data); // Редактируем сообщение
                }
            
                // Сохраняем последний update_id в файл, чтобы избежать повторов
                file_put_contents(OFFSET_FILE, $lastUpdateId);
            }
        }
}

    sleep(2); // Ждем 2 секунды перед следующим запросом


// Функция для отправки сообщения
function sendMessage($data) {
    $urlQuery = "https://api.telegram.org/bot7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4/sendMessage";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlQuery);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Получаем ответ как строку
    curl_setopt($ch, CURLOPT_POST, 1); // Это POST-запрос
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Передача данных
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Временно отключаем проверку сертификата
    curl_exec($ch); // Выполняем запрос
    curl_close($ch); // Закрываем сессию
}

// Функция для редактирования текста сообщения
function editMessageText($data) {
    $urlQuery = "https://api.telegram.org/bot7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4/editMessageText";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $urlQuery);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // Получаем ответ как строку
    curl_setopt($ch, CURLOPT_POST, 1); // Это POST-запрос
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data)); // Передача данных
    curl_exec($ch); // Выполняем запрос
    curl_close($ch); // Закрываем сессию
}
if (curl_errno($ch)) {
    error_log("cURL error: " . curl_error($ch));
}

    

   


  
    




//---------------------------------//





// t: && cd T:\OSPanel\domains\Doonpablobot && php bot.php 
// cd C:\ospanel\domains\Doonpablobot && php bot.php
?>