# Контракты входящих ACTION (сайт → Bitrix24 CRM)

Отдельная папка с **предметными** описаниями тел запросов для каждого поддерживаемого `ACTION`. Это дополняет транспортный контракт (токен, HMAC, dedup и т.д.) из [`modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md`](../modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md).

## Транспорт: токен на каждый запрос

Любой `POST` на `endpoint.php` (включая `GET_CONTACT_ID`, `UPDATE_COMPANY`, `CRM_METHOD` и т.д.) **обязан** передавать секрет синхронизации, иначе портал ответит **`403 sync_forbidden`** (`reason_detail`: `missing_or_empty_token`), даже если в CRM уже заполнены все UF.

Способы (достаточно одного, порядок приоритета см. `site_requests_handler.php`):

- заголовок **`X-SYNC-TOKEN`**: значение = **`sync_token`** из `site_sync_settings.local.php` на портале;
- или в теле запроса (JSON или `x-www-form-urlencoded`): **`sync_token`**, **`SYNC_TOKEN`**, **`_TOKEN`**, **`_SYNC_TOKEN`** (первое непустое).

Заполнение **`UF_CRM_…`** в CRM **не подставляет** токен автоматически: его должен добавить **HTTP-клиент сайта** (curl, Guzzle, фоновая задача и т.п.).

## Источник истины в коде

- Диспетчер ACTION: `modules/yomerch.b24.inbound/lib/InboundEndpoint.php` (`InboundEndpoint::processRequest`)
- Нормализация полезной нагрузки: `modules/yomerch.b24.inbound/lib/InboundPayloadNormalizer.php`
- Алиасы имён ACTION: `modules/yomerch.b24.inbound/lib/InboundActionDispatcher.php`

## Карта документов

| ACTION | Файл |
|--------|------|
| `UPDATE_GROUP` (алиас `UPDATE_STATUS_GROUP`) | [actions/UPDATE_GROUP.md](actions/UPDATE_GROUP.md) |
| `GET_CONTACT_ID` | [actions/GET_CONTACT_ID.md](actions/GET_CONTACT_ID.md) |
| `UPDATE_COMPANY` | [actions/UPDATE_COMPANY.md](actions/UPDATE_COMPANY.md) |
| `CRM_METHOD` | [actions/CRM_METHOD.md](actions/CRM_METHOD.md) |
| `DELETE_CONTACT` | Делегирование в `OnlineService\LocalApplicationHandler` (см. ADR R12, UF `contact.delete_site_ref`) |

## Соответствие пользовательским полям CRM

Имена UF подтягиваются из `modules/yomerch.b24.contract/lib/config/uf_mapping.php` через `UfMap`. В документах где нужно — указаны и человекочитаемые ключи карты (`company.site_element_id`), и конкретные коды UF для сверки на портале.
