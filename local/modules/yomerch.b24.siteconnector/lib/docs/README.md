# Документация интеграции (CRM)

Дополняйте этот каталог по мере появления сценариев, уникальных для портала.

Общая архитектура и каналы: [`bitrix-docker/www/local/sync/docs/channels.md`](../../../../bitrix-docker/www/local/sync/docs/channels.md).

Предметный контракт: [`functional-contract.md`](../../../../bitrix-docker/www/local/sync/docs/functional-contract.md) — раздел **6** при необходимости здесь (URL входа, проверка источника, окружения). Шпаргалка на аварию (опционально): [`runbook.md`](../../../../bitrix-docker/www/local/sync/docs/runbook.md) на стороне сайта.

Локальные документы B24:

- [`sync-package-readme.md`](sync-package-readme.md) — набор модулей `yomerch.b24.*` и роль `yomerch.b24.siteconnector`.
- [`b24-inbound.md`](b24-inbound.md) — требования к входящему каналу сайт -> CRM.
- Пакет для внешней команды сайта: `local/bitrix24-external-developers/` (корень репозитория `local`).
