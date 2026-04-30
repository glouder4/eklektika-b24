# R11 — задачи: самодиагностика inbound

## Цель

Одностраничный отчёт о готовности входящего канала к записи логов и базовой конфигурации.

## Subtasks

| ID | Описание | Статус |
|----|----------|--------|
| R11-01 | Реализовать `local/inbound-test.php` (admin/CLI, без утечки секретов) | done |
| R11-02 | Проверки: `site_sync_settings.local.php`, секрет (длина), пути endpoint, writable `local/logs`, тест записи | done |
| R11-03 | ADR R11 + ссылка из отчёта / краткая подсказка по POST | done |

## Next steps for Team Lead

- При smoke на стенде: открыть `inbound-test.php` под админом и убедиться, что `log_path_writable` = true.
- При `false` — выставить права на `local/logs` пользователю веб-сервера или смотреть fallback `/tmp/inbound-b24.log` на Linux.
