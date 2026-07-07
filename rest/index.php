<?php
header('Content-Type: application/json');

// Всегда возвращаем конфигурацию push-сервера
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