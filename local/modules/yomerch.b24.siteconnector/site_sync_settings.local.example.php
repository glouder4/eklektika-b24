<?php

/**
 * Скопируйте в site_sync_settings.local.php на портале и задайте sync_token.
 * Это единственный source-of-truth для локальных runtime-настроек интеграции (в т.ч. `sync_token`).
 * Файл site_sync_settings.local.php в git не коммитится.
 *
 * @return array<string, mixed>
 */
return [
    'site_url' => 'https://example.com',
    // Source-of-truth для секрета входящего канала; синоним контракта сайта (тот же секрет):
    // 'inbound_secret' => '',
    'sync_token' => '',
    'sync_debug' => false,
    'sync_trace' => false,

    // Опционально: входящая безопасность B24→сайт (см. `local/bitrix24-external-developers/b24_site_contracts_yomerch.md`).
    // 'allow_inbound_without_secret' => false,

    // HMAC над сырым телом (`php://input`), если включено:
    // 'inbound_hmac_secret' => '',

    // Проверка skew часов по `X-Sync-Timestamp` / `sync_ts`:
    // 'inbound_max_skew_seconds' => 0,

    // Жёстко: синхронизация только если токен в заголовке `X-SYNC-TOKEN` (не принимать `sync_token` в теле):
    // 'inbound_require_header_token' => false,

    // Dedup по `REQUEST_ID` / заголовку `X-Sync-Request-Id` (HTTP 409 на повтор):
    // 'inbound_dedup_ttl_seconds' => 0,
    // На production рекомендуется указать безопасный путь за пределами webroot:
    // 'inbound_dedup_store_path' => '',

    // Опционально: legacy fallback для просрочек сделок (см. `yomerch.b24.deals` + `cron/check_deals_status.php`):
    // 'deals_fallback_on_mismatch' => false,
];
