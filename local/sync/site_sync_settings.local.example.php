<?php

/**
 * Скопируйте в site_sync_settings.local.php на портале и задайте sync_token
 * (совпадает с inbound_secret / настройками на сайте).
 * Файл site_sync_settings.local.php в git не коммитится.
 *
 * @return array<string, mixed>
 */
return [
    'site_url' => 'https://example.com',
    'sync_token' => '',
    'sync_debug' => false,
    'sync_trace' => false,
];
