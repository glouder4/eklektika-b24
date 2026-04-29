# R10-SUB-02: Паритет UF и валидация идентификатора элемента для UPDATE_COMPANY

- Parent: `../tasks/r10-inbound-action-contracts-task.md`
- Status: `done`

## Goal

Выровнять поведение входящего канала с картой UF и исходящим `CompanySync`: принимать legacy UF в теле запроса, искать компанию по каноническому и legacy UF, при записи выставлять оба UF одним значением; отвергать пустой и нулевой идентификатор элемента.

## DoD

- Изменения в `InboundEndpoint.php` без регрессии сигнатур публичного API модуля.
- Раздел `UPDATE_COMPANY` в `bitrix24-inbound-from-site-contracts/actions/` описывает новое поведение.
