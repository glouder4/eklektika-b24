# Документация интеграции (CRM)

Дополняйте этот каталог по мере появления сценариев, уникальных для портала.

Общая архитектура и каналы: [`bitrix-docker/www/local/sync/docs/channels.md`](../../../../bitrix-docker/www/local/sync/docs/channels.md).

Предметный контракт: [`functional-contract.md`](../../../../bitrix-docker/www/local/sync/docs/functional-contract.md) — раздел **6** при необходимости здесь (URL входа, проверка источника, окружения). Шпаргалка на аварию (опционально): [`runbook.md`](../../../../bitrix-docker/www/local/sync/docs/runbook.md) на стороне сайта.

Локальные документы B24:

- [`sync-package-readme.md`](sync-package-readme.md) — набор модулей `yomerch.b24.*` и роль `yomerch.b24.siteconnector`.
- [`b24-inbound.md`](b24-inbound.md) — требования к входящему каналу сайт -> CRM.
- [`adr-r6-inbound-request-logging.md`](adr-r6-inbound-request-logging.md) — архитектурное решение по контракту inbound-логирования ACTION/body/payload.
- [`progress-r6-inbound-request-logging.md`](progress-r6-inbound-request-logging.md) — прогресс и блокеры по инициативе расширения логов endpoint.
- [`tasks/r6-inbound-request-logging-task.md`](tasks/r6-inbound-request-logging-task.md) — верхнеуровневая задача и дерево подзадач.
- [`subtasks/r6-sub-01-logging-contract-and-sanitizer.md`](subtasks/r6-sub-01-logging-contract-and-sanitizer.md) — подзадача по контракту логов и sanitizer/truncation.
- [`subtasks/r6-sub-02-endpoint-instrumentation.md`](subtasks/r6-sub-02-endpoint-instrumentation.md) — подзадача по внедрению endpoint-логирования.
- [`subtasks/r6-sub-03-stand-validation-and-leakage-audit.md`](subtasks/r6-sub-03-stand-validation-and-leakage-audit.md) — подзадача валидации и аудита утечек для inbound-логов.
- [`adr-r7-external-contract-reconciliation.md`](adr-r7-external-contract-reconciliation.md) — архитектурное решение по сверке с обновленным внешним контрактом.
- [`progress-r7-external-contract-reconciliation.md`](progress-r7-external-contract-reconciliation.md) — прогресс новой волны reconciliation.
- [`tasks/r7-external-contract-reconciliation-task.md`](tasks/r7-external-contract-reconciliation-task.md) — верхнеуровневая задача R7 и дерево подзадач.
- [`subtasks/r7-sub-01-source-of-truth-inventory.md`](subtasks/r7-sub-01-source-of-truth-inventory.md) — инвентаризация источников контракта и recovery external docs.
- [`subtasks/r7-sub-02-inbound-contract-delta-matrix.md`](subtasks/r7-sub-02-inbound-contract-delta-matrix.md) — матрица дельт между кодом и внешним контрактом.
- [`subtasks/r7-sub-03-remediation-and-evidence-plan.md`](subtasks/r7-sub-03-remediation-and-evidence-plan.md) — план remediation и артефактов верификации.
- [`adr-r8-inbound-execution-parity.md`](adr-r8-inbound-execution-parity.md) — R8: транспорт/безопасность inbound (HMAC, skew, dedup, токен, `X-Sync-Request-Id`).
- [`progress-r8-inbound-execution-parity.md`](progress-r8-inbound-execution-parity.md) — прогресс R8 и delivery-chain step 7.
- [`tasks/r8-inbound-execution-parity-task.md`](tasks/r8-inbound-execution-parity-task.md) — верхнеуровневая задача R8 и подзадачи `R8-SUB-*`.
- [`subtasks/r8-sub-01-tier-a-baseline-and-settings-example.md`](subtasks/r8-sub-01-tier-a-baseline-and-settings-example.md) — Tier A + пример настроек.
- [`subtasks/r8-sub-02-r7-matrix-transport-rows.md`](subtasks/r8-sub-02-r7-matrix-transport-rows.md) — обновление матрицы R7 по транспортным строкам после R8.
- [`subtasks/r8-sub-03-stand-evidence-pack.md`](subtasks/r8-sub-03-stand-evidence-pack.md) — стендовые доказательства опциональных путей.
- [`adr-r9-portal-runtime-local-settings-policy.md`](adr-r9-portal-runtime-local-settings-policy.md) — **R9:** на портале в `local/` не используем `getenv`/`.env` для интеграции; канон — `site_sync_settings.local.php`.
- [`progress-r9-portal-runtime-local-settings-policy.md`](progress-r9-portal-runtime-local-settings-policy.md) — прогресс R9.
- [`tasks/r9-portal-runtime-local-settings-policy-task.md`](tasks/r9-portal-runtime-local-settings-policy-task.md) — задача R9 и подзадачи верификации.
- [`subtasks/r9-sub-01-static-verify-no-getenv.md`](subtasks/r9-sub-01-static-verify-no-getenv.md) — статическая проверка отсутствия `getenv`.
- [`subtasks/r9-sub-02-docs-and-example-alignment.md`](subtasks/r9-sub-02-docs-and-example-alignment.md) — доки и пример настроек под политику R9.
- [`adr-r10-inbound-action-payload-contracts.md`](adr-r10-inbound-action-payload-contracts.md) — **R10:** предметные контракты тел входящих ACTION (отдельная папка `local/bitrix24-inbound-from-site-contracts/`) и паритет UF для `UPDATE_COMPANY`.
- [`progress-r10-inbound-action-contracts.md`](progress-r10-inbound-action-contracts.md) — прогресс R10.
- [`tasks/r10-inbound-action-contracts-task.md`](tasks/r10-inbound-action-contracts-task.md) — задача R10 и подзадачи `R10-SUB-*`.
- [`subtasks/r10-sub-01-action-contract-docs.md`](subtasks/r10-sub-01-action-contract-docs.md) — документация по ACTION.
- [`subtasks/r10-sub-02-legacy-site-element-parity.md`](subtasks/r10-sub-02-legacy-site-element-parity.md) — паритет canonical/legacy UF для идентификатора элемента сайта.
- [`adr-r5-cutover-slice.md`](adr-r5-cutover-slice.md) — финальное архитектурное решение по закрытию R5 среза.
- [`progress-r5-cutover-slice.md`](progress-r5-cutover-slice.md) — актуальный прогресс и явный оставшийся блокер.
- [`tasks/r5-cutover-slice-task.md`](tasks/r5-cutover-slice-task.md) — верхнеуровневая задача и дерево подзадач.
- [`subtasks/r5-stand-evidence-execution.md`](subtasks/r5-stand-evidence-execution.md) — блокирующая операционная подзадача.
- Пакет для внешней команды сайта: `local/bitrix24-external-developers/` (корень рабочей копии `local`). Путь **в `.gitignore`** (`/local/bitrix24-external-developers`): в чистом git-клоне каталога может не быть, пока его не положили из канонического источника; при гидратации дерево доступно для `R7-W2` (см. [`progress-r7-external-contract-reconciliation.md`](progress-r7-external-contract-reconciliation.md), [`subtasks/r7-sub-01-source-of-truth-inventory.md`](subtasks/r7-sub-01-source-of-truth-inventory.md), `TIER_B_DELIVERY_INTAKE_CHECKLIST.md`).
