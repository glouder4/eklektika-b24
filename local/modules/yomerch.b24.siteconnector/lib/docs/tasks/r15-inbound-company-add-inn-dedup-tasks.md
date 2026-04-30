# R15 — `crm.company.add` и ИНН / холдинг

## Выполнено

- [x] R15-01 — Карта UF: `company.head_company_flag`, `company.holding_group_members`.
- [x] R15-02 — При duplicate INN и «головной» компании: создание дочерней, связь через `company.holding`, синхронизация `company.holding_group_members` на всех участниках.
- [x] R15-03 — `InboundEndpoint`: маршрут `DELETE_CONTACT` → `LocalApplicationHandler`.
- [x] R15-04 — Документация `CRM_METHOD.md`, ADR R15.

## Осталось

- [ ] R15-05 — Сайт: обработка новых `reason_code` и привязка контакта к **`child_company_id`** из ответа.

## Проверка

- ИНН головной компании с включённым `UF_CRM_1758028888` → новая компания, одинаковый список в `UF_CRM_1776426878` у головной и дочерних.
- ИНН без признака головной → `company_add_use_existing_for_contact`, тот же `result` что и CRM ID компании для `crm.contact.company.add`.
