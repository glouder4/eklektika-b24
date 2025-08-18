
<?php
return array (
    'pull_s1' => 'BEGIN GENERATED PUSH SETTINGS. DON\'T DELETE COMMENT!!!!',
    'pull' =>
        array (
            'value' =>
                array (
                    'path_to_listener' => 'http://#DOMAIN#/bitrix/sub/',
                    'path_to_listener_secure' => 'https://#DOMAIN#/bitrix/sub/',
                    'path_to_modern_listener' => 'http://#DOMAIN#/bitrix/sub/',
                    'path_to_modern_listener_secure' => 'https://#DOMAIN#/bitrix/sub/',
                    'path_to_mobile_listener' => 'http://#DOMAIN#:8893/bitrix/sub/',
                    'path_to_mobile_listener_secure' => 'https://#DOMAIN#:8894/bitrix/sub/',
                    'path_to_websocket' => 'ws://#DOMAIN#/bitrix/subws/',
                    'path_to_websocket_secure' => 'wss://#DOMAIN#/bitrix/subws/',
                    'path_to_publish' => 'http://bitrixvm-test.eklektika.local:8895/bitrix/pub/',
                    'path_to_publish_web' => 'http://#DOMAIN#/bitrix/rest/',
                    'path_to_publish_web_secure' => 'https://#DOMAIN#/bitrix/rest/',
                    'nginx_version' => '4',
                    'nginx_command_per_hit' => '100',
                    'nginx' => 'Y',
                    'nginx_headers' => 'N',
                    'push' => 'Y',
                    'websocket' => 'Y',
                    'signature_key' => '4G1YnFp6S0nrETX4AVWESSIZol1cfO9nro75gGvXdmtlOQEGAl1DxMTpLoe5x5VTlV0EvyorOnV2obVGZ5UM80IvUPMuLoQPnEKIaemTv3EjFQHhR3JGlytq1MTC4e49',
                    'signature_algo' => 'sha1',
                    'guest' => 'N',
                ),
        ),
    'pull_e1' => 'END GENERATED PUSH SETTINGS. DON\'T DELETE COMMENT!!!!',
    'session' =>
        array (
            'value' =>
                array (
                    'mode' => 'default',
                ),
            'readonly' => true,
        ),
    'utf_mode' =>
        array (
            'value' => true,
            'readonly' => true,
        ),
    'default_charset' =>
        array (
            'value' => false,
            'readonly' => false,
        ),
    'no_accelerator_reset' =>
        array (
            'value' => false,
            'readonly' => false,
        ),
    'http_status' =>
        array (
            'value' => false,
            'readonly' => false,
        ),
    'cache_flags' =>
        array (
            'value' =>
                array (
                    'config_options' => 3600,
                    'site_domain' => 3600,
                ),
            'readonly' => false,
        ),
    'cookies' =>
        array (
            'value' =>
                array (
                    'secure' => false,
                    'http_only' => true,
                ),
            'readonly' => false,
        ),
    'exception_handling' =>
        array (
            'value' =>
                array (
                    'debug' => true,
                    'handled_errors_types' => 30711,
                    'exception_errors_types' => 1,
                    'ignore_silence' => false,
                    'assertion_throws_exception' => true,
                    'assertion_error_type' => 256,
                    'log' =>
                        array (
                            'settings' =>
                                array (
                                    'file' => 'bitrix/modules/error.log',
                                    'log_size' => 1000000,
                                ),
                        ),
                ),
            'readonly' => false,
        ),
    'crypto' =>
        array (
            'value' =>
                array (
                    'crypto_key' => '4a0b45926c047f043ac90dada6c064ac',
                ),
            'readonly' => true,
        ),
    'smtp' =>
        array (
            'value' =>
                array (
                    'enabled' => true,
                    'debug' => true,
                ),
            'readonly' => true,
        ),
    'connections' =>
        array (
            'value' =>
                array (
                    'default' =>
                        array (
                            'host' => 'localhost',
                            'database' => 'glouder_ekl_b24',
                            'login' => 'glouder_ekl_b24',
                            'password' => 'p*lu%3BrVsKy',
                            'className' => '\\Bitrix\\Main\\DB\\MysqliConnection',
                            'options' => '2',
                        ),
                ),
        ),
);