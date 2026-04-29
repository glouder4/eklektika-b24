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

## Статус

- Входящий канал: `yomerch.b24.inbound/lib/site_requests_handler.php` + `InboundEndpoint.php` (URL — `yomerch.b24.inbound/endpoint.php`).
- Контрактный endpoint для внешних вызовов: `/local/modules/yomerch.b24.inbound/endpoint.php`.
