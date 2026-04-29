# Progress: R10 inbound ACTION payload contracts

- Initiative: контракты тел запросов по ACTION + паритет UF для `UPDATE_COMPANY`
- Last updated: 2026-04-30

## Done

- ADR **R10** принят (`adr-r10-inbound-action-payload-contracts.md`).
- Каталог **`local/bitrix24-inbound-from-site-contracts/`**: README и файлы по ACTION (`UPDATE_GROUP`, `GET_CONTACT_ID`, `UPDATE_COMPANY`, `CRM_METHOD`).
- Реализация в **`InboundEndpoint`**: разбор идентификатора элемента сайта из канонического UF, legacy UF или `SITE_ELEMENT_ID`; поиск компании по обоим UF; синхронная запись обоих UF при обновлении; отказ от пустого значения и `'0'`.
- Задача **R10-INBOUND-ACTION-CONTRACTS** и подзадачи **R10-SUB-01** / **R10-SUB-02** закрыты (Team Lead аудит совмещён с этой волной).

## Open

- Расширение каталога ACTION в коде без синхронного обновления папки контрактов (process risk под `R7-W2` catalog drift).

## Blockers

- Нет.
