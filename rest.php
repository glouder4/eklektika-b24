<?php
// Обработчик для REST-запросов в старой версии Битрикса

// Проверяем, что запрос к /rest/pull.config.get.json
if (strpos($_SERVER['REQUEST_URI'], '/rest/pull.config.get.json') !== false) {
    header('Content-Type: application/json');
    echo json_encode([
        'server' => [
            'channel_id' => 'test-channel',
            'server_uri' => 'https://bitrix.yomerch.ru',
            'websocket_uri' => 'wss://bitrix.yomerch.ru:8010/bitrix/subws/',
            'publish_uri' => 'http://bitrix.yomerch.ru:9010/bitrix/pub/',
            'listener_uri' => 'http://bitrix.yomerch.ru:8010/bitrix/sub/',
        ],
        'channels' => [
            [
                'id' => 'test-channel',
                'type' => 'public',
            ]
        ]
    ]);
    exit;
}

// Для других REST-запросов
header('Content-Type: application/json');
echo json_encode([
    'error' => 'NO_AUTH_FOUND',
    'error_description' => 'Authorization failed!'
]);