# Контракты входящих ACTION (сайт → Bitrix24 CRM)

Отдельная папка с **предметными** описаниями тел запросов для каждого поддерживаемого `ACTION`. Это дополняет транспортный контракт (токен, HMAC, dedup и т.д.) из [`modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md`](../modules/yomerch.b24.siteconnector/lib/docs/b24-inbound.md).

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

## Соответствие пользовательским полям CRM

Имена UF подтягиваются из `modules/yomerch.b24.contract/lib/config/uf_mapping.php` через `UfMap`. В документах где нужно — указаны и человекочитаемые ключи карты (`company.site_element_id`), и конкретные коды UF для сверки на портале.
