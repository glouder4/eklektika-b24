<?php
return array (
    'utf_mode' => array(
        'value' => true,
        'readonly' => true,
    ),
    'default_charset' => array(
        'value' => false,
        'readonly' => false,
    ),
    'no_accelerator_reset' => array(
        'value' => false,
        'readonly' => false,
    ),
    'http_status' => array(
        'value' => false,
        'readonly' => false,
    ),
    'cache_flags' => array(
        'value' => array(
            'config_options' => 3600,
            'site_domain' => 3600,
        ),
        'readonly' => false,
    ),
    'cookies' => array(
        'value' => array(
            'secure' => true,
            'http_only' => true,
        ),
        'readonly' => false,
    ),
    'session' => array(
        'value' => array(
            'mode' => 'default',
            'cookie' => array(
                'name' => 'PHPSESSID',
                'secure' => true,
                'httponly' => true,
            ),
        ),
        'readonly' => false,
    ),
    'exception_handling' => array(
        'value' => array(
            'debug' => true,
            'handled_errors_types' => 30711,
            'exception_errors_types' => 1,
            'ignore_silence' => false,
            'assertion_throws_exception' => true,
            'assertion_error_type' => 256,
            'log' => array(
                'settings' => array(
                    'file' => 'bitrix/modules/error.log',
                    'log_size' => 1000000,
                ),
            ),
        ),
        'readonly' => false,
    ),
    'crypto' => array(
        'value' => array(
            'crypto_key' => '4a0b45926c047f043ac90dada6c064ac',
        ),
        'readonly' => true,
    ),
    'smtp' => array(
        'value' => array(
            'enabled' => true,
            'debug' => true,
        ),
        'readonly' => true,
    ),
    'connections' => array(
        'value' => array(
            'default' => array(
                'host' => '127.0.0.1',
                'database' => 'dbbitrixyo',
                'login' => 'yobitrixusr',
                'password' => '4W-NmEhQH.vI0p64etPz',
                'className' => '\\Bitrix\\Main\\DB\\MysqliConnection',
                'options' => '2',
            ),
        ),
    ),
    'rest' => array(
        'value' => array(
            'signature_key' => '3dbW4MBqa3sSCNLZ5UisKxIJBaAxxzHkW61gmeH6ASo5WZVsXdYKLnssgW0wsFZku7Dnp8R8KZ05NIGRg9Slepmx4telOeHYtF5JggG1CsBLcP8PGKhgBxlM6qOQtCtf',
            'session_auth' => true,
        ),
        'readonly' => false,
    ),
    'pull' => array(
        'value' => array(
            // ПОДПИСКА (listener) - порты 8010-8015
            'path_to_listener' => 'http://bitrix.yomerch.ru:8010/bitrix/sub/',
            'path_to_listener_secure' => 'https://bitrix.yomerch.ru:8010/bitrix/sub/',
            'path_to_modern_listener' => 'http://bitrix.yomerch.ru:8010/bitrix/sub/',
            'path_to_modern_listener_secure' => 'https://bitrix.yomerch.ru:8010/bitrix/sub/',
            'path_to_mobile_listener' => 'http://bitrix.yomerch.ru:8010/bitrix/sub/',
            'path_to_mobile_listener_secure' => 'https://bitrix.yomerch.ru:8010/bitrix/sub/',
            'path_to_websocket' => 'ws://bitrix.yomerch.ru:8010/bitrix/subws/',
            'path_to_websocket_secure' => 'wss://bitrix.yomerch.ru:8010/bitrix/subws/',

            // ПУБЛИКАЦИЯ (publish) - порты 9010-9011
            'path_to_publish' => 'http://bitrix.yomerch.ru:9010/bitrix/pub/',
            'path_to_publish_web' => 'http://bitrix.yomerch.ru:9010/bitrix/rest/',
            'path_to_publish_web_secure' => 'https://bitrix.yomerch.ru:9010/bitrix/rest/',

            // Остальное
            'nginx_version' => '4',
            'nginx_command_per_hit' => '100',
            'nginx' => 'Y',
            'nginx_headers' => 'N',
            'push' => 'Y',
            'websocket' => 'Y',
            'signature_key' => '3dbW4MBqa3sSCNLZ5UisKxIJBaAxxzHkW61gmeH6ASo5WZVsXdYKLnssgW0wsFZku7Dnp8R8KZ05NIGRg9Slepmx4telOeHYtF5JggG1CsBLcP8PGKhgBxlM6qOQtCtf',
            'signature_algo' => 'sha1',
            'auth_lifetime' => 86400,   // 24 часа
            'auth_method' => 'guest',   // разрешить гостевой доступ
            'guest' => 'Y',             // включить гостевой режим
        ),
    ),
    'session' => array(
        'value' => array(
            'mode' => 'default',
        ),
        'readonly' => true,
    ),
);