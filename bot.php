<?php
// Устанавливаем неограниченное время выполнения скрипта
set_time_limit(0);

// Определяем константы: токен бота Telegram и файл для хранения последнего обработанного update_id
define("TG_TOKEN", "7992724027:AAG--G_-NV7YZ74VTce2egibEJLjst-sli4");
define("OFFSET_FILE", "last_update.txt");

// Подключение к базе данных MySQL с использованием PDO
try {
    $pdo = new PDO("mysql:host=localhost;dbname=telegram_bott;charset=utf8", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    exit("Ошибка подключения к БД: " . $e->getMessage());
}

// Получаем последнее обработанное update_id из файла или начинаем с 0, если файл отсутствует
$lastUpdateId = file_exists(OFFSET_FILE) ? (int) file_get_contents(OFFSET_FILE) : 0;

// Основной бесконечный цикл для опроса Telegram API
while (true) {
    // Формируем URL для запроса обновлений, начиная с update_id > $lastUpdateId
    $urlQueryTE = "https://api.telegram.org/bot" . TG_TOKEN . "/getUpdates?offset=" . ($lastUpdateId + 1);
    $response = file_get_contents($urlQueryTE);
    $data = json_decode($response, true);

    // Если есть новые обновления, обрабатываем их
    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $updateId = $update['update_id'];
            // Если update_id больше последнего, обновляем переменную и файл
            if ($updateId > $lastUpdateId) {
                $lastUpdateId = $updateId;
                file_put_contents(OFFSET_FILE, $lastUpdateId);
                
                // Если получено текстовое сообщение – вызываем функцию processMessage
                if (isset($update['message']['text'], $update['message']['chat']['id'])) {
                    processMessage($update['message']);
                }
                // Если получен callback запрос – вызываем функцию processCallback
                if (isset($update['callback_query'])) {
                    processCallback($update['callback_query']);
                }
            }
        }
    }
    // Задержка в 2 секунды между запросами к API
    sleep(2);
}

// Функция обработки входящих сообщений от пользователей
function processMessage($message) {
    global $pdo;
    // Получаем идентификатор чата и имя пользователя (если оно не задано, ставим "unknown")
    $TG_USER_ID = $message['chat']['id'];
    $TG_USERNAME = $message['chat']['username'] ?? 'unknown';
    $textMessageR = $message['text'];
    
    // Сохраняем пользователя в базу данных, обновляя имя пользователя, если оно уже существует
    try {
        $stmt = $pdo->prepare("INSERT INTO users (user_id, username) VALUES (:user_id, :username) ON DUPLICATE KEY UPDATE username = :username");
        $stmt->execute(["user_id" => $TG_USER_ID, "username" => $TG_USERNAME]);
    } catch (PDOException $e) {
        error_log("Ошибка БД: " . $e->getMessage());
    }

    // Если сообщение содержит команду "/menu", отправляем главное меню с кнопками
    if ($textMessageR === "/menu") {
        $keyboard = getMainMenu();
        sendMessageWithKeyboard($TG_USER_ID, "Выберите категорию:", $keyboard);
    }
    // Обработка команды /orders (вывод заказов пользователя)
    elseif ($textMessageR === "/orders") {
        $stmt = $pdo->prepare("SELECT item, price FROM orders WHERE user_id = :user_id");
        $stmt->execute(["user_id" => $TG_USER_ID]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($orders)) {
            sendMessage($TG_USER_ID, "У вас пока нет заказов.");
        } else {
            $messageText = "Ваши заказы:\n";
            foreach ($orders as $order) {
                $messageText .= "{$order['item']} - {$order['price']}₸\n";
            }
            sendMessage($TG_USER_ID, $messageText);
        } }
         // Обработка команды /help
    elseif ($textMessageR === "/help") {
        // Формирование клавиатуры с кнопкой-ссылкой
        $keyboard = [
            "inline_keyboard" => [
                [
                    ["text" => "Помощь", "url" => "https://inlnk.ru/ELXE7N"]
                ]
            ]
        ];
        sendMessageWithKeyboard($TG_USER_ID, "Поддержка:", $keyboard);
    }
}

// Функция обработки callback запросов от inline-кнопок
function processCallback($callback) {
    global $pdo;
    // Получаем идентификатор пользователя из callback
    $TG_USER_ID = $callback['message']['chat']['id'];
    $callbackData = $callback['data'];

    
    // Если выбран пункт "cart" - выводим содержимое корзины
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
                $cartText .= "{$item['item']} - {$item['price']}₸\n";
                $totalPrice += $item['price'];
            }
            $cartText .= "\nИтоговая цена: {$totalPrice}₸";
            sendMessage($TG_USER_ID, $cartText);
        }
    } 

    if ($callbackData === 'checkout') {
        // Сообщение, которое будет отправлено пользователю
        $message = "Ваш заказ принят!";
        
        // Отправка сообщения пользователю через Telegram API
        sendMessage($TG_USER_ID, $message);
    }

    // Если выбран пункт "clear_cart" - очищаем корзину
    elseif ($callbackData === "clear_cart") {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE user_id = :user_id");
        $stmt->execute(["user_id" => $TG_USER_ID]);
        sendMessage($TG_USER_ID, "Корзина очищена.");
    } 
    // Если callback соответствует одному из пунктов меню, выводим дополнительные варианты
    elseif (isset(getMenuOptions()[$callbackData])) {
        $keyboard = ["inline_keyboard" => [getMenuOptions()[$callbackData]]];
        sendMessageWithKeyboard($TG_USER_ID, "Выберите вариант:", $keyboard);
    } 
    // Если callback соответствует выбранному товару, добавляем его в корзину
    else {
        // Массив с ценами на товары
        $prices = [
            "Маргарита" => 500, "Пепперони" => 600,
            "Чизбургер" => 300, "БигМак" => 350,
            "Филадельфия" => 700, "Калифорния" => 650,
            "Кола" => 150, "Сок" => 200,
            "Карбонара" => 450, "Болоньезе" => 500,
            "Чизкейк" => 300, "Тирамису" => 350,
            "Начос" => 250, "Куриные крылышки" => 400
        ];
        // Если выбранный товар есть в списке цен
        if (isset($prices[$callbackData])) {
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, item, price) VALUES (:user_id, :item, :price)");
            $stmt->execute(["user_id" => $TG_USER_ID, "item" => $callbackData, "price" => $prices[$callbackData]]);
            sendMessage($TG_USER_ID, "Добавлено в корзину: $callbackData ({$prices[$callbackData]}₸)");
        }
    }
}

// Функция для формирования главного меню с кнопками
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
                ["text" => "Паста", "callback_data" => "pasta"],
                ["text" => "Десерты", "callback_data" => "desserts"],
                ["text" => "Закуски", "callback_data" => "snacks"]
            ],
            [
                ["text" => "🛒 Корзина", "callback_data" => "cart"],
                ["text" => "❌ Очистить корзину", "callback_data" => "clear_cart"]
            ],
            [
                ["text" => "✅ Оформить заказ", "callback_data" => "checkout"]
            ]
        ]
    ];
}

// Функция для формирования меню с вариантами товаров для каждой категории
function getMenuOptions() {
    return [
        "pizza" => [
            ["text" => "Маргарита - 500₸", "callback_data" => "Маргарита"],
            ["text" => "Пепперони - 600₸", "callback_data" => "Пепперони"]
        ],
        "burger" => [
            ["text" => "Чизбургер - 300₸", "callback_data" => "Чизбургер"],
            ["text" => "БигМак - 350₸", "callback_data" => "БигМак"]
        ],
        "sushi" => [
            ["text" => "Филадельфия - 700₸", "callback_data" => "Филадельфия"],
            ["text" => "Калифорния - 650₸", "callback_data" => "Калифорния"]
        ],
        "drinks" => [
            ["text" => "Кола - 150₸", "callback_data" => "Кола"],
            ["text" => "Сок - 200₸", "callback_data" => "Сок"]
        ],
        "pasta" => [
            ["text" => "Карбонара - 450₸", "callback_data" => "Карбонара"],
            ["text" => "Болоньезе - 500₸", "callback_data" => "Болоньезе"]
        ],
        "desserts" => [
            ["text" => "Чизкейк - 300₸", "callback_data" => "Чизкейк"],
            ["text" => "Тирамису - 350₸", "callback_data" => "Тирамису"]
        ],
        "snacks" => [
            ["text" => "Начос - 250₸", "callback_data" => "Начос"],
            ["text" => "Куриные_крылышки - 400₸", "callback_data" => "Куриные крылышки"]
        ]
    ];
}

// Функция для отправки сообщения с клавиатурой (inline кнопками)
function sendMessageWithKeyboard($chatId, $text, $keyboard) {
    sendMessage($chatId, $text, json_encode($keyboard));
}

// Функция для отправки обычного сообщения
function sendMessage($chatId, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'reply_markup' => $keyboard
    ];
    // Отправка запроса на метод sendMessage API Telegram с использованием GET-запроса
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
//t: && cd \ospanel\domains\Doonpablobot && php bot.php
?>
