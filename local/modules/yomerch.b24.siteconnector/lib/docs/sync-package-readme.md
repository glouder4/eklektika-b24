# Интеграция B24 ↔ сайт — набор модулей `yomerch.b24.*`

Контракт для внешней команды сайта: **`local/bitrix24-external-developers/`** (см. `BITRIX24_EXTERNAL_TEAM_HANDOFF.md`).

## Модули (каждый — отдельный каталог в `local/modules/`)

| Модуль | Ответственность |
|--------|-----------------|
| **yomerch.b24.base** | Общие вспомогательные классы: `LocalApplicationHandler`, `RestCall` |
| **yomerch.b24.contract** | UF-семантика: `UfMap`, `lib/config/uf_mapping.php` |
| **yomerch.b24.deals** | Просрочки сделок: `DealExpiryHandler`, `DealExpiryAgent` |
| **yomerch.b24.inbound** | Канал **сайт → CRM**: `lib/site_requests_handler.php`, `lib/InboundEndpoint.php`, публичный `endpoint.php` |
| **yomerch.b24.statussync** | Аддон live status-sync (iblock статусы): `OnlineService\StatusSync\CompanyStatusSync` |
| **yomerch.b24.outbound** | Канал **CRM → сайт**: `CompanySync`, `ContactSync`, `ManagerUserSync`, `OutboundRequest`, … |
| **yomerch.b24.siteconnector** | Настройки `site_sync_settings*.php`, агрегатор автозагрузки подмодулей, регистрация CRM/iblock (`register_bitrix_handlers.php`) |

Автозагрузка: каждый подмодуль подключает свой `include.php` (идемпотентно). `yomerch.b24.siteconnector/lib/bootstrap_autoload.php` подтягивает все подмодули для сценариев без полного `init` (напрямую `site_requests_handler`). Порядок в `modules/bootstrap.php` по glob: `base` → `contract` → `deals` → `inbound` → `legacy`(no-op, deprecated) → `outbound` → `siteconnector` → `statussync`.

`yomerch.b24.legacy` выведен из эксплуатации: legacy-классы удалены, active-path синхронизации статусов вынесен в `yomerch.b24.statussync`.

## Входящий URL

`/local/modules/yomerch.b24.inbound/endpoint.php` → `lib/site_requests_handler.php`.
