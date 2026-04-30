# ADR R11: самодиагностика входящего канала (`local/inbound-test.php`)

## Статус

Принято (реализация в репозитории).

## Контекст

Операторам нужно быстро проверить на портале: наличие `site_sync_settings.local.php`, разрешён ли секрет, доступна ли запись в `local/logs/inbound-b24.log`, существует ли публичная точка `endpoint.php`, без ручного чтения кода.

## Решение

- Добавить скрипт **`/local/inbound-test.php`**, выводящий структурированный отчёт (JSON + human-readable при `?format=text`).
- Опционально **`?handler_smoke=1`** (веб): исходящий GET к `endpoint.php` ожидает **405** и добавляет в `inbound-b24.log` реальную строку `[trace] site_requests_handler.reject.method_not_allowed`.
- CLI: **`php local/inbound-test.php --handler-smoke https://хост`** — то же через HTTP-клиент.
- **Доступ:** только **администратор Bitrix** в веб-режиме или **CLI** с корректным `DOCUMENT_ROOT` (автоопределение от пути файла).
- **Секреты не логировать:** для `sync_token` / `inbound_secret` — только факт наличия и длина строки.
- Файл не заменяет смоук по реальному POST на `endpoint.php`; дополняет его проверкой окружения.

## Последствия

- Риск: любой скрипт в `/local/` теоретически доступен по URL — снят ограничением `IsAdmin()` для HTTP.
- Рекомендация: после отладки при желании удалить файл или ограничить по IP на уровне веб-сервера.

## Связанные артефакты

- `modules/yomerch.b24.siteconnector/lib/docs/tasks/r11-inbound-self-diagnostics-tasks.md`
- `b24-inbound.md` — путь логов `local/logs/inbound-b24.log`
