# Task: R10 inbound ACTION payload contracts

- Task ID: `R10-INBOUND-ACTION-CONTRACTS`
- Status: `done`
- Priority: high (стабильность обмена сайт ↔ CRM)
- Owner: Tech Lead + inbound owner
- ADR: `../adr-r10-inbound-action-payload-contracts.md`
- Progress: `../progress-r10-inbound-action-contracts.md`

## Goal

Зафиксировать для каждого входящего ACTION предметный контракт тела запроса в отдельной папке и устранить расхождение legacy/canonical UF для идентификатора элемента сайта при `UPDATE_COMPANY`.

## Inputs

- `../../../../bitrix24-inbound-from-site-contracts/`
- `../../../yomerch.b24.inbound/lib/InboundEndpoint.php`
- `../../../yomerch.b24.contract/lib/config/uf_mapping.php`

## Subtasks tree

- [x] `R10-SUB-01` Документация по ACTION в отдельной папке
  - Subtask doc: `../subtasks/r10-sub-01-action-contract-docs.md`
  - Status: `done` (2026-04-30).

- [x] `R10-SUB-02` Паритет UF и валидация идентификатора элемента для `UPDATE_COMPANY`
  - Subtask doc: `../subtasks/r10-sub-02-legacy-site-element-parity.md`
  - Status: `done` (2026-04-30).

## Team Lead audit (2026-04-30)

- Контракты покрывают ровно карту диспетчера в `InboundEndpoint::processRequest`.
- Код отражает документированное поведение по dual-UF и отказу от `'0'`.
