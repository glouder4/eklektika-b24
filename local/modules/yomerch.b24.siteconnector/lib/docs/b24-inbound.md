# B24 входящий канал (сайт -> CRM)

Этот файл фиксирует правила для входящих запросов на стороне Bitrix24.
Подробный общий контракт обмена хранится на стороне сайта:
`bitrix-docker/www/local/sync/docs/functional-contract.md`.

## Точка входа

- Реализация на портале B24: модуль **`local/modules/yomerch.b24.inbound/`** (`lib/site_requests_handler.php`, `lib/InboundEndpoint.php`).

## Базовые требования

- Проверять источник запроса и секрет (если используется общий токен).
- Обрабатывать повторные доставки идемпотентно.
- Логировать успешные и ошибочные операции в отдельный лог канала.

## R8 inbound execution parity (security/transport — runtime)

Initiative **R8** (`adr-r8-inbound-execution-parity.md`, `tasks/r8-inbound-execution-parity-task.md`). Deterministic baseline for `site_requests_handler.php` + `InboundSecurity` + `InboundDedupStore`:

- **Secret:** from `site_sync_settings.local.php`: `sync_token` or alias `inbound_secret` (`InboundSecurity::resolveSecret`). Empty secret → `HTTP 503` `sync_misconfigured`, unless dev override flag `allow_inbound_without_secret` is enabled in the same local settings file (truthy scalar).
- **Method:** non-`POST` → `HTTP 405` `method_not_allowed`, `Allow: POST`.
- **Sync token:** compared to secret via `X-SYNC-TOKEN` header and/or body keys `_TOKEN`, `_SYNC_TOKEN`, `sync_token`, `SYNC_TOKEN` (first non-empty). Mismatch → `HTTP 403` `sync_forbidden`. Optional `inbound_require_header_token`: token must also appear in `X-SYNC-TOKEN` or `403`.
- **HMAC (optional):** if `inbound_hmac_secret` set — `X-Sync-Signature` required, 64-char hex lowercase SHA-256 HMAC of **raw** request body; else `sync_signature_missing` / `sync_signature_invalid` → `HTTP 403`.
- **Clock skew (optional):** if `inbound_max_skew_seconds` > 0 — timestamp from `X-Sync-Timestamp` or body fields (`sync_ts`, `SYNC_TS`, `_SYNC_TS`, `TIMESTAMP`, and `_SYNC_*` aliases per code); invalid → `sync_timestamp_invalid`; outside window → `sync_timestamp_expired` (`HTTP 403`).
- **Dedup (optional):** if `inbound_dedup_ttl_seconds` > 0 and store resolvable — key from normalized `X-Sync-Request-Id` / `X-Sync-Request-Uuid` / body `REQUEST_ID` / `request_id` / `_REQUEST_ID`; duplicate → `HTTP 409`, `error=duplicate_request`, `reason_code=dedup_duplicate`. Store errors → fail-open (logged).
- **Response header:** `X-Sync-Request-Id` = incoming id or `trace_id` fallback.
- **Dispatcher ACTION** (unchanged contract slice): `InboundEndpoint` + `InboundActionDispatcher` — canonical `UPDATE_GROUP`, `GET_CONTACT_ID`, `UPDATE_COMPANY`, `CRM_METHOD`; alias `UPDATE_STATUS_GROUP -> UPDATE_GROUP`.
- **`InboundPayloadNormalizer`:** `invalid_payload` / `unknown_action` → `HTTP 400`; known action domain failures default to `HTTP 200` unless handler sets `http_status`.

Remaining Tier B vs runtime gaps (**ACTION catalog breadth vs dispatcher** — product decision; wording; SLIs) stay under **`R7-W2`** — `r7-sub-02-inbound-contract-delta-matrix.md`, `r7-sub-03-remediation-and-evidence-plan.md` — and should be re-adjudicated; transport toggles delivered under **R8** are documented above and in `adr-r8-inbound-execution-parity.md`.

## Контракт логирования inbound endpoint (R6)

Источник решения: `adr-r6-inbound-request-logging.md`.

### Куда пишутся логи

- Основной sink: `local/logs/inbound-b24.log`.
- Fallback sink при недоступности основного: `/tmp/inbound-b24.log`.
- Формат: одна JSON-структура контекста на строку (`[trace] <event> {...}`).

### Обязательный request-событие

Для каждого входящего запроса должен фиксироваться `site_requests_handler.payload.received` с минимумом полей:

- `trace_id`, `correlation_id`, `cutover_label`, `event`, `ts`;
- `request_method`, `request_uri`, `content_type`;
- `action` (`ACTION`), `method` (`METHOD`, если есть);
- `payload_keys`, `params_keys`, `fields_keys`;
- `body_raw_len`, `body_logged_len`, `body_truncated`, `body_truncated_from`, `body`.

### Политика редактирования (redaction)

Перед записью `body`/payload:

- Маскировать секреты по ключам (case-insensitive):
  - `X_SYNC_TOKEN`, `sync_token`, `token`, `authorization`, `password`, `api_key`, `secret`.
- Маскировать контактные поля:
  - `EMAIL`, `PHONE`, `LEGAL_ENTITY_EMAIL`, `LEGAL_ENTITY_PHONE` (на границе запросов фактические имена ключей по-прежнему `LEGAN_ENTITY_*` для совместимости с текущим inbound).

- Для бинарных/крупных значений (`fileData[1]`, base64-like, `CONTENT`) записывать маркер:
  - `[REDACTED_BINARY len=<N>]`.
- Политика применяется рекурсивно ко всем вложенным структурам.

### Ограничение длины и truncation

- `MAX_LOG_BODY_BYTES = 16384` (16 KiB) для сериализованного `body`.
- При превышении:
  - в `body` сохраняется префикс и добавляется `... [TRUNCATED <original_len> bytes]`;
  - выставляются `body_truncated=true` и `body_truncated_from=<original_len>`.
- Даже при truncation событие не пропускается.

## HTTP и бизнес-ошибки (контрактный минимум)

- Для валидного запроса с распознанным `ACTION` транспортный ответ всегда `HTTP 200`, а бизнес-результат передается в JSON (`success`, `reason_code`, `error`/`message`).
- Для невалидного транспорта/контракта (`unknown_action`, невалидный payload) ожидается `HTTP 400` с `error_code` в теле.

### Детерминированная матрица outcomes

- `transport_error`:
  - `HTTP != 200` или техническая ошибка доставки/парсинга;
  - `success=0`, `error_code` из transport-класса;
  - retry разрешен только для `429/503` (экспоненциальный backoff с jitter) и сетевых ошибок.
- `domain_failure`:
  - `HTTP 200`, `success=0`, обязательно `reason_code`;
  - retry запрещен по умолчанию (требуется ручная/предметная компенсация).
- `domain_success`:
  - `HTTP 200`, `success=1`.

### Наблюдение по кейсу UPDATE_CONTACT (trace 2026-04-29)

- В рантайме зафиксировано: `http_code=200`, `event=update_contact_result`, `success=0`, `reason_code=update_contact_user_not_found`.
- Это согласуется с разделением на транспортную успешность (`HTTP 200`) и бизнес-ошибку (`success=0`) в текущей реализации B24 outbound.
- Политика реакции: `UPDATE_CONTACT` c `success=0` и `reason_code` трактуется как `domain_failure` (без автоматического retry), с обязательным логированием `reason_code/error_code/http_status`.

## Статус

- Входящий канал: `yomerch.b24.inbound/lib/site_requests_handler.php` + `InboundEndpoint.php` (URL — `yomerch.b24.inbound/endpoint.php`).
- Контрактный endpoint для внешних вызовов: `/local/modules/yomerch.b24.inbound/endpoint.php`.
