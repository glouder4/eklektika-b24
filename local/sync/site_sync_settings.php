<?php

/**
 * Исходящая синхронизация CRM → сайт (URL, токен, флаги отладки).
 *
 * Не затирайте рабочие значения в этом файле «в ноль» без явного запроса владельца портала.
 * Для секретов и окружений, которые не должны попадать в git, используйте site_sync_settings.local.php
 * (init.php подмешивает его поверх этого return).
 *
 * Переопределение без дублирования ключей: скопируйте site_sync_settings.local.example.php → site_sync_settings.local.php
 *
 * @return array{
 *     site_url?: string,
 *     sync_token?: string,
 *     sync_debug?: bool|string|int,
 *     sync_trace?: bool|string|int
 * }
 *
 * sync_debug: pre()+die() в исходящем запросе + запись в local/logs/b24-to-site-sync.log.
 * sync_trace: только файл (без die). Если sync_trace выключен, при sync_debug файл всё равно пишется.
 */
return [
    'site_url' => 'https://yomerch.ru',
    /** Переопределите в site_sync_settings.local.php; = inbound_secret на сайте (local/sync/config.local.php). */
    'sync_token' => '',
    'sync_debug' => false,
    'sync_trace' => false,
];
