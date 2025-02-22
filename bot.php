<?php

set_time_limit(0);

define("TG_TOKEN", "7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4");
define("OFFSET_FILE", "last_update.txt");

// Подключение к базе данных
try {
    $pdo = new PDO("mysql:host=localhost;dbname=telegram_bott;charset=utf8", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("Ошибка подключения к БД: " . $e->getMessage());
}

$lastUpdateId = file_exists(OFFSET_FILE) ? (int) file_get_contents(OFFSET_FILE) : 0;

while (true) {
    $urlQueryTE = "https://api.telegram.org/bot" . TG_TOKEN . "/getUpdates?offset=" . ($lastUpdateId + 1);
    $response = file_get_contents($urlQueryTE);
    $data = json_decode($response, true);

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $updateId = $update['update_id'];
            if ($updateId > $lastUpdateId) {
                $lastUpdateId = $updateId;
                file_put_contents(OFFSET_FILE, $lastUpdateId);
                
                if (isset($update['message']['text'], $update['message']['chat']['id'])) {
                    processMessage($update['message']);
                }
                if (isset($update['callback_query'])) {
                    processCallback($update['callback_query']);
                }
            }
        }
    }
    sleep(2);
}

function processMessage($message) {
    global $pdo;
    $TG_USER_ID = $message['chat']['id'];
    $TG_USERNAME = $message['chat']['username'] ?? 'unknown';
    $textMessageR = $message['text'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (user_id, username) VALUES (:user_id, :username) ON DUPLICATE KEY UPDATE username = :username");
        $stmt->execute(["user_id" => $TG_USER_ID, "username" => $TG_USERNAME]);
    } catch (PDOException $e) {
        error_log("Ошибка БД: " . $e->getMessage());
    }

    if ($textMessageR === "/menu") {
        $keyboard = getMainMenu();
        sendMessageWithKeyboard($TG_USER_ID, "Выберите категорию:", $keyboard);
    }
}

function processCallback($callback) {
    global $pdo;
    $TG_USER_ID = $callback['message']['chat']['id'];
    $callbackData = $callback['data'];

    if ($callbackData === "cart") {
        $stmt = $pdo->prepare("SELECT item, price FROM orders WHERE user_id = :user_id");
        $stmt->execute(["user_id" => $TG_USER_ID]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            sendMessage($TG_USER_ID, "Ваша корзина пуста.");
        } else {
            $totalPrice = 0;
            $cartText = "Ваши товары:\n";
            foreach ($items as $item) {
                $cartText .= "{$item['item']} - {$item['price']}₽\n";
                $totalPrice += $item['price'];
            }
            $cartText .= "\nИтоговая цена: {$totalPrice}₽";
            sendMessage($TG_USER_ID, $cartText);
        }
    } elseif ($callbackData === "clear_cart") {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE user_id = :user_id");
        $stmt->execute(["user_id" => $TG_USER_ID]);
        sendMessage($TG_USER_ID, "Корзина очищена.");
    } elseif (isset(getMenuOptions()[$callbackData])) {
        $keyboard = ["inline_keyboard" => [getMenuOptions()[$callbackData]]];
        sendMessageWithKeyboard($TG_USER_ID, "Выберите вариант:", $keyboard);
    } else {
        $prices = [
            "Маргарита" => 500, "Пепперони" => 600,
            "Чизбургер" => 300, "БигМак" => 350,
            "Филадельфия" => 700, "Калифорния" => 650,
            "Кола" => 150, "Сок" => 200
        ];
        if (isset($prices[$callbackData])) {
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, item, price) VALUES (:user_id, :item, :price)");
            $stmt->execute(["user_id" => $TG_USER_ID, "item" => $callbackData, "price" => $prices[$callbackData]]);
            sendMessage($TG_USER_ID, "Добавлено в корзину: $callbackData ({$prices[$callbackData]}₽)");
        }
    }
}

function getMainMenu() {
    return [
        "inline_keyboard" => [
            [
                ["text" => "Пицца", "callback_data" => "pizza"],
                ["text" => "Бургер", "callback_data" => "burger"],
                ["text" => "Суши", "callback_data" => "sushi"],
                ["text" => "Напитки", "callback_data" => "drinks"]
            ],
            [
                ["text" => "🛒 Корзина", "callback_data" => "cart"],
                ["text" => "❌ Очистить корзину", "callback_data" => "clear_cart"]
            ]
        ]
    ];
}

function getMenuOptions() {
    return [
        "pizza" => [
            ["text" => "Маргарита - 500₽", "callback_data" => "Маргарита"],
            ["text" => "Пепперони - 600₽", "callback_data" => "Пепперони"]
        ],
        "burger" => [
            ["text" => "Чизбургер - 300₽", "callback_data" => "Чизбургер"],
            ["text" => "БигМак - 350₽", "callback_data" => "БигМак"]
        ],
        "sushi" => [
            ["text" => "Филадельфия - 700₽", "callback_data" => "Филадельфия"],
            ["text" => "Калифорния - 650₽", "callback_data" => "Калифорния"]
        ],
        "drinks" => [
            ["text" => "Кола - 150₽", "callback_data" => "Кола"],
            ["text" => "Сок - 200₽", "callback_data" => "Сок"]
        ]
    ];
}

function sendMessageWithKeyboard($chatId, $text, $keyboard) {
    sendMessage($chatId, $text, json_encode($keyboard));
}

function sendMessage($chatId, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => $keyboard
    ];
    file_get_contents("https://api.telegram.org/bot" . TG_TOKEN . "/sendMessage?" . http_build_query($data));
}
while (true) {
    $urlQueryTE = "https://api.telegram.org/bot" . TG_TOKEN . "/getUpdates?offset=" . ($lastUpdateId + 1);
    $response = file_get_contents($urlQueryTE);
    
    if ($response === false) {
        error_log("Ошибка запроса к Telegram API");
        sleep(2);
        continue;
    }

    $data = json_decode($response, true);
    
    if ($data === null) {
        error_log("Ошибка декодирования JSON: " . json_last_error_msg());
        sleep(2);
        continue;
    }

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $updateId = $update['update_id'];
            if ($updateId > $lastUpdateId) {
                $lastUpdateId = $updateId;
                file_put_contents(OFFSET_FILE, $lastUpdateId);
                
                if (isset($update['message']['text'], $update['message']['chat']['id'])) {
                    processMessage($update['message']);
                }
                if (isset($update['callback_query'])) {
                    processCallback($update['callback_query']);
                }
            }
        }
    }
    sleep(2);
}

?>
